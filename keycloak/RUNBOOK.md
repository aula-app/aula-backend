# Keycloak at sso.aula.de 

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

### 2.1 Back up first, always

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
Maven in stage 1, so no JDK is needed on the host, only Docker.

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
Password logins to aula are unaffected: they never touch Keycloak.

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

Deploying the provider is not enough, it has to be an execution in the realm's browser
flow, **after** the Identity Provider Redirector. Config lives in the database, so it
survives image changes; it only needs doing once (and again after any restore).

### 3.0 The quick way: admin console

Easier than the API and does the same thing.

1. https://sso.aula.de/auth/admin/ → realm **aula** → **Authentication**
2. **Flows** tab → open **browser**
3. On the **Identity Provider Redirector** row, use the **+** (or the row's kebab menu) →
   **Add step**
4. Search for **`Aula: Forward IdP Error to Client`** (category *Brokering*, provider id
   `aula-idp-error-handler`). If it is not in the list, the image does not carry the
   provider, go back to section 2.2.
5. Drag it so it sits **immediately below Identity Provider Redirector**, still at the top
   level of the flow, above the `forms` subflow
6. Set its requirement to **Alternative**
7. Changes save immediately; no restart needed

Then run the smoke test in 3.5. Target layout:

```
browser
├── Cookie                                  Alternative
├── Kerberos                                Disabled
├── Identity Provider Redirector            Alternative
├── Aula: Forward IdP Error to Client       Alternative   ← here
└── forms                                   Alternative
    └── …
```

### 3.1 Get an admin token

```bash
TOK=$(sudo docker exec keycloak-keycloak-1 curl -s \
  -X POST "http://localhost:8080/auth/realms/master/protocol/openid-connect/token" \
  -d "client_id=admin-cli" -d "username=<admin>" -d "password=<password>" \
  -d "grant_type=password" | sed -n 's/.*"access_token":"\([^"]*\)".*/\1/p')
[ -n "$TOK" ] && echo OK || echo "auth failed"
```

The `KEYCLOAK_ADMIN` / `KEYCLOAK_ADMIN_PASSWORD` values in `docker-compose.yml` are the
literal strings `admin` / `CHANGE_ME_ADMIN` and are **not** the live credentials. Those
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

Confirm with the same GET as 3.2, the handler must sit immediately after the redirector
and before the forms subflow.

### 3.5 Smoke test

Click **Login with SSO** in aula, then **cancel** at Eduplaces. Expected: back at the aula
login screen showing "Sign-in was cancelled…", URL `…/login?sso_error=login_cancelled`.
Not expected: a Keycloak "We are sorry…" page.

Then do a *successful* SSO login too. The authenticator must be a no-op on the happy path,
and that is the failure mode worth catching: a misplaced execution can break every login,
not just cancelled ones.

---

## 3A. Logout: let Keycloak chain it to the IdP

Separate from the cancel handling, and needed for **logout** to work at all.

aula used to build the Eduplaces `end_session` URL itself and pass the whole aula Keycloak
logout URL as `post_logout_redirect_uri`. That value carries a fresh `id_token_hint` on
every logout, so it differs each time and no allowlist entry can ever match it. Eduplaces
answered:

```
Logout failed because query parameter post_logout_redirect_uri is not a
whitelisted as a post_logout_redirect_uri for the client.
```

Propagating a brokered logout is Keycloak's job. `fix/463-sso-logout-idp-propagation`
removes aula's hand-built chain (and the `sso_idp_id_token` column it needed); the backend
now returns only the aula Keycloak logout URL. Two pieces of configuration make Keycloak
carry it upstream, and both are one-time.

**Do 3A.2 before 3A.1.** Setting the Logout URL is what makes Keycloak start calling
Eduplaces; if the allowlist entry is not there yet, logout goes straight back to the
Eduplaces error page. Registering the URI first means there is no broken window.

Symptom when only the code fix is deployed and neither step is done: logout succeeds and
returns you to the aula login screen, but signing in again takes one click at Eduplaces
instead of asking for credentials. Keycloak's session ended, Eduplaces' did not.

### 3A.0 The client must not do front-channel logout

`AuthenticationManager.browserLogout()` logs the clients out first and returns early if
that produced a response. The identity-provider logout is only reached afterwards, so a
client with **Front channel logout** enabled silently prevents any brokered logout from
ever happening: the IdP's Logout URL is correct and simply never consulted.

Clients → **aula-backend** → Settings → Logout settings → **Front channel logout: off**.

The client had it on with no `frontchannel.logout.url` set, so it was doing nothing except
short-circuiting the logout. Observed in the server log as:

```
Logging out: <user> (<session>)
frontchannel logout to: aula-backend
All clients have been logged out ...
LOGOUT
```

with no `org.keycloak.broker` activity at all. If brokered logout ever stops working
again, look for that pattern first: a clean `LOGOUT` event with no broker lines means the
IdP step was skipped, not that it failed.

### 3A.1 Keycloak: give the IdP a Logout URL

Admin console → realm **aula** → **Identity providers** → **eduplaces** → **Logout URL**.

Set it to the provider's `end_session_endpoint`, which for the sandbox is:

```
https://auth.sandbox.eduplaces.dev/oauth2/sessions/logout
```

Confirm the current value from the provider's own discovery document rather than trusting
this file:

```bash
curl -s https://auth.sandbox.eduplaces.dev/.well-known/openid-configuration \
  | python3 -c "import json,sys; print(json.load(sys.stdin)['end_session_endpoint'])"
```

Production Eduplaces is a different issuer, read its discovery document the same way.

### 3A.2 Eduplaces: whitelist Keycloak's logout response endpoint

With a Logout URL set, Keycloak sends its **own** static endpoint as
`post_logout_redirect_uri`:

```
https://sso.aula.de/auth/realms/aula/broker/eduplaces/endpoint/logout_response
```

That URL never changes, so Eduplaces can register it once. Add it as an allowed
post-logout redirect URI for the aula client in the Eduplaces app configuration. This half
cannot be done from our side.

The path above is Keycloak's documented shape (`{base}/realms/{realm}/broker/{alias}/
endpoint/logout_response`, with `/auth` from `KC_HTTP_RELATIVE_PATH`). Rather than trust
it, read the value Keycloak actually sends: set the Logout URL, attempt one logout, and if
Eduplaces rejects it the error page's own URL contains the exact
`post_logout_redirect_uri` it received. Copy that verbatim into the allowlist.

### 3A.2b What the Eduplaces sandbox does not honour

Measured 2026-08-13 against `auth.sandbox.eduplaces.dev`. Both findings are theirs, not
ours, and neither is fixable from aula or Keycloak:

- **`end_session` does not end the session.** A direct `GET /oauth2/sessions/logout`
  redirects to `explore.sandbox.eduplaces.dev/de` and leaves the session live. (A bare
  request with no `id_token_hint` is one an OP may ignore, so this is strong evidence
  rather than proof.)
- **`prompt=login` is ignored.** aula sets `sso_force_login` at logout, the next login
  sends `force_login=true`, the backend turns that into `prompt=login`, and Keycloak
  forwards it, confirmed by following the redirect chain, which reaches
  `https://auth.sandbox.eduplaces.dev/oauth2/auth` carrying `prompt=login`. Eduplaces
  still re-uses the existing session without re-authenticating.

Consequence: after logging out of aula, the next SSO login needs one click rather than
credentials, because the Eduplaces session outlives everything we control. On a shared
device the next person inherits it. Raise it with Eduplaces; there is no configuration on
our side that changes it.

Do **not** bother adding `prompt` to the identity provider's *Forwarded query parameters*,
it is already forwarded without any configuration.

### 3A.3 Verify

Log in through Eduplaces, then log out of aula. Expected: no Eduplaces error page, and a
second login prompts for credentials again rather than signing straight back in. If it
signs straight back in, the Eduplaces session was not ended, so check 3A.1.

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
