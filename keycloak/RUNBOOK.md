# Keycloak at sso.aula.de — how to keep it running correctly

Production SSO for every aula school. Host `aula-sso`, SSH on port 2244, stack under
`/opt/keycloak` (`docker-compose.yml`), Postgres 16 alongside in `keycloak-keycloak-db-1`.

The same host also runs the aula app itself (backend, legacy backend, frontend, MariaDB)
under `/opt/aula`. Restarting Keycloak does not touch those, but be aware they share a box.

---

## 1. Why the image is custom

Keycloak does not forward a brokered identity-provider error to the client. When someone
clicks **cancel** at Eduplaces, Eduplaces returns `error=access_denied` to
`…/broker/eduplaces/endpoint` and Keycloak renders its own "We are sorry… Access denied"
page. The browser never returns to aula, so nothing in the backend can react.

`keycloak/idp-error-authenticator/` in this repo is an authenticator SPI that closes the
gap. Sitting after the **Identity Provider Redirector** it is a no-op on a normal login,
and on a forwarded IdP error it redirects to the client's `redirect_uri` with
`error=access_denied`, carrying the original `state`.

The aula side already handles the rest:

- `SsoController::callback()` maps `?error=access_denied` → `{frontend}/login?sso_error=login_cancelled`
- the frontend has messages for `login_cancelled` and `sso_provider_error`

So production runs `aula-keycloak:<version>`, not the stock image.

### Version floor: 26.6.1

Below 26.6.1 the `kc_idp_hint` flow loops before the authenticator is ever reached
(keycloak/keycloak#47955). Deploying the provider onto an older server reinstates a flow
that cannot work. **Do not drop below 26.6.1.**

Keep these three in sync when upgrading:

| Where | What |
|---|---|
| `keycloak/idp-error-authenticator/pom.xml` | `<keycloak.version>` |
| `keycloak/idp-error-authenticator/Dockerfile` | `FROM quay.io/keycloak/keycloak:<version>` (stage 2) |
| `/opt/keycloak/docker-compose.yml` on the host | `image: aula-keycloak:<version>` |

---

## 2. Deploy or upgrade

Everything below runs on the host. `ssh -i <key> -p 2244 ansible@sso.aula.de`.

### 2.1 Back up first — always

The first start of a new Keycloak migrates the database schema, and that is not reversible
without a restore.

```bash
TS=$(date +%Y%m%d-%H%M%S)
sudo mkdir -p /opt/keycloak/upgrade-backup-$TS
sudo cp /opt/keycloak/docker-compose.yml /opt/keycloak/upgrade-backup-$TS/docker-compose.yml.orig
sudo docker exec keycloak-keycloak-db-1 pg_dump -U keycloak -d keycloak \
  | sudo tee /opt/keycloak/upgrade-backup-$TS/keycloak-db.sql > /dev/null

# sanity: expect ~90
sudo grep -c "CREATE TABLE" /opt/keycloak/upgrade-backup-$TS/keycloak-db.sql
```

Also export the auth flows before touching them (see 3.3).

### 2.2 Build the image

The source lives at `/opt/keycloak/idp-error-authenticator` on the host (mirror of this
repo's `keycloak/idp-error-authenticator/`). The Dockerfile compiles the provider with
Maven in stage 1, so no JDK is needed on the host — only Docker.

```bash
cd /opt/keycloak/idp-error-authenticator
sudo docker build -t aula-keycloak:26.6.1 .
```

Expect this line during `kc.sh build`, which is what proves the provider was picked up:

```
KC-SERVICES0047: aula-idp-error-handler
  (de.aula.keycloak.authenticator.AulaIdpErrorHandlerAuthenticatorFactory)
  is implementing the internal SPI authenticator
```

The warning is expected: the authenticator SPI is internal and may change between Keycloak
versions. That is precisely why the version floor matters and why upgrades need a smoke
test rather than a blind bump.

### 2.3 Swap and recreate

```bash
cd /opt/keycloak
sudo sed -i "s|image: quay.io/keycloak/keycloak:26.5.5|image: aula-keycloak:26.6.1|" docker-compose.yml
sudo docker compose up -d keycloak
```

Downtime is roughly a minute: SSO logins fail while the container restarts and migrates.
Password logins to aula are unaffected — they never touch Keycloak.

### 2.4 Verify

```bash
sudo docker ps --format "{{.Names}}\t{{.Image}}\t{{.Status}}" | grep keycloak
sudo docker logs keycloak-keycloak-1 2>&1 | grep -iE "started|error|fatal|aula-idp" | tail
```

Want: `Keycloak 26.6.1 … started`, the `aula-idp-error-handler` line, no errors.

From anywhere:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://sso.aula.de/auth/realms/aula/.well-known/openid-configuration
curl -s -o /dev/null -w "%{http_code}\n" https://sso.aula.de/auth/realms/aula/protocol/openid-connect/certs
```

Both must be `200`.

---

## 3. Wire the authenticator into the browser flow

Deploying the provider is not enough — it has to be an execution in the realm's browser
flow, **after** the Identity Provider Redirector. Config lives in the database, so it
survives image changes; it only needs doing once (and again after any restore).

### 3.1 Get an admin token

```bash
TOK=$(sudo docker exec keycloak-keycloak-1 curl -s \
  -X POST "http://localhost:8080/auth/realms/master/protocol/openid-connect/token" \
  -d "client_id=admin-cli" -d "username=<admin>" -d "password=<password>" \
  -d "grant_type=password" | sed -n 's/.*"access_token":"\([^"]*\)".*/\1/p')
[ -n "$TOK" ] && echo OK || echo "auth failed"
```

The `KEYCLOAK_ADMIN` / `KEYCLOAK_ADMIN_PASSWORD` values in `docker-compose.yml` are the
literal strings `admin` / `CHANGE_ME_ADMIN` and are **not** the live credentials — those
env vars only seed an admin on a first-ever start. The real password lives in the database.

### 3.2 Find the flow and the redirector's position

```bash
kc() { sudo docker exec keycloak-keycloak-1 curl -s -H "Authorization: Bearer $TOK" "$@"; }

kc "http://localhost:8080/auth/admin/realms/aula/authentication/flows/browser/executions" \
  | python3 -m json.tool | grep -E '"displayName"|"index"|"level"|"id"'
```

### 3.3 Back the flow up before editing

```bash
kc "http://localhost:8080/auth/admin/realms/aula/authentication/flows/browser/executions" \
  | sudo tee /opt/keycloak/backup-flows-$(date +%Y%m%d-%H%M%S).json > /dev/null
```

There is already a `backup-flows-20260806-164842.json` on the host from an earlier session.

### 3.4 Add the execution

```bash
sudo docker exec keycloak-keycloak-1 curl -s -X POST \
  -H "Authorization: Bearer $TOK" -H "Content-Type: application/json" \
  -d '{"provider":"aula-idp-error-handler"}' \
  "http://localhost:8080/auth/admin/realms/aula/authentication/flows/browser/executions/execution"
```

Then move it directly below **Identity Provider Redirector** with repeated
`…/executions/<id>/raise-priority` calls, and set its requirement to `ALTERNATIVE`
(via `PUT …/authentication/flows/browser/executions`).

Confirm with the same GET as 3.2 — the handler must sit immediately after the redirector
and before the forms subflow.

### 3.5 Smoke test

Click **Login with SSO** in aula, then **cancel** at Eduplaces. Expected: back at the aula
login screen showing "Sign-in was cancelled…", URL `…/login?sso_error=login_cancelled`.
Not expected: a Keycloak "We are sorry…" page.

Then do a *successful* SSO login too. The authenticator must be a no-op on the happy path,
and that is the failure mode worth catching — a misplaced execution can break every login,
not just cancelled ones.

---

## 4. Rollback

```bash
TS=<the timestamp you backed up under>
cd /opt/keycloak
sudo cp upgrade-backup-$TS/docker-compose.yml.orig docker-compose.yml
sudo docker compose up -d keycloak
```

If the schema was already migrated, the old image will refuse to start against the new
schema. Restore the database too:

```bash
sudo docker compose stop keycloak
sudo docker exec -i keycloak-keycloak-db-1 psql -U keycloak -d postgres \
  -c "DROP DATABASE keycloak;" -c "CREATE DATABASE keycloak OWNER keycloak;"
sudo docker exec -i keycloak-keycloak-db-1 psql -U keycloak -d keycloak \
  < upgrade-backup-$TS/keycloak-db.sql
sudo docker compose up -d keycloak
```

---

## 5. Known issues on this host

- **Placeholder secrets in `docker-compose.yml`.** `KC_DB_PASSWORD: CHANGE_ME` and
  `KEYCLOAK_ADMIN_PASSWORD: CHANGE_ME_ADMIN` are committed literally. The admin one is
  inert (the live password is in the database), but the database password is the value
  Keycloak actually connects with. Rotate both, ideally in the same maintenance window as
  an upgrade.
- **`.docker-compose.yml.swp` is present**, meaning someone has or had the file open in
  vim. Check for a stale editor session before editing, or two people will overwrite each
  other.
- **A commented-out service block near the top** of `docker-compose.yml` also carries an
  `image:` line, so a careless `sed` over the file rewrites it too. Harmless, but check
  `git diff`-style before committing changes.
- **The authenticator SPI is internal to Keycloak.** Every upgrade needs the smoke test in
  3.5, not just a health check.

---

## 6. History

| When | What |
|---|---|
| 2026-07-07 | Authenticator written and built; jar staged on the host, never deployed |
| 2026-08-06 | An orphaned `aula-idp-error-handler` execution was found in the browser flow with no provider behind it, so Keycloak could not instantiate it and **all** SSO logins failed with a 400. Execution deleted, flows backed up to `backup-flows-20260806-164842.json` |
| 2026-08-13 | Upgraded 26.5.5 → 26.6.1 on the custom `aula-keycloak` image with the provider baked in. DB backed up to `upgrade-backup-20260813-094109/` |
