# Identity Provider Directory Sync

How a school gets into aula from an identity provider's directory, and how it
stays in step afterwards.

Nothing below is specific to one vendor. A provider is a block in
`config/idp.php` plus two classes, an `IdentityDirectory` and a
`WebhookAdapter`. No migrations, no routes, and no change to `SchoolImport`,
`TenantResolver` or the syncs. Eduplaces is currently the only implementation
and is used for the examples.

A tenant picks its provider with `tenants.sso_provider`, the same alias Keycloak
brokers under, so a tenant's login and its directory always agree.

Eduplaces specification: <https://developer.eduplaces.de/idm/webhooks>.
For the login flow itself, see [sso-architecture.md](sso-architecture.md).

## Onboarding a school

```
1. tenant is created                → one admin row (admin1_*), instance code
2. instance code sent to the school
3. first SSO login                  → claims that admin row
4. the whole school is imported     → rooms + users, on the queue
5. every later login                → account already exists, nothing created
```

Step 3 triggers step 4, and it fires exactly once: `bootstrapIdpTenant()` runs
only while no user in the tenant has an `sso_sub`.

**Nothing about the school is configured by hand.** `tenants.idp_school_id` is
set at step 3 by `learnSchoolId()`, from the `school` claim in the upstream
id_token. All a new tenant needs is `sso_provider` set to the Eduplaces IdP
alias: the first login reports which school it came from, and that is what gets
imported.

Because the column is unique, a login whose school already belongs to another
tenant is refused rather than silently moving the school across.

One consequence: the very first login has to be the instance-code flow. An
IdP-initiated launch resolves its tenant *by* `idp_school_id`, so it only
works once a school has been learned.

**One admin, not two.** The first SSO login provisions no second account beside
the seeded admin: that row becomes the account, keeping its `UserLevel::Admin`
and gaining `sso_sub` and `idp_user_id`. `SchoolImport` never demotes it, so an
account the directory reports as an ordinary teacher stays an aula admin.

**The import runs on the queue.** `bootstrapIdpTenant()` dispatches
`ImportSchoolForTenant` and the login returns at once. Every later login has to
find its account already imported, so the frontend polls
`GET /api/v2/auth/idp/import-status` and holds the user on a setup screen while
`ready` is false:

```json
{ "ready": false, "status": "running", "rooms": 0, "users": 0,
  "error": null, "started_at": "...", "finished_at": null }
```

`status` is `pending`, `running`, `completed` or `failed`. A tenant that syncs
from no directory reports `ready: true` at once and is never blocked.

## Groups are rooms

An Eduplaces group ("Klasse 5a") is an aula **room**:

| Eduplaces        | aula                                          |
|------------------|-----------------------------------------------|
| group            | `au_rooms` row, keyed by `idp_group_id` |
| group membership | `au_rel_rooms_users`                          |
| member's role    | entry in `au_users_basedata.roles`            |

`au_groups` plays no part in the integration and is never written to.

Every imported user is also enrolled in the school-wide room
(`au_rooms.type = 1`), the same as any locally provisioned user.

The `roles` column is a list of `{"role": N, "room": "<room hash_id>"}`. Each
membership contributes one entry; entries for rooms outside Eduplaces are left
untouched.

Role mapping, from `services.eduplaces.role_userlevels`:

| Eduplaces role | `userlevel` | per-room `role` |
|----------------|-------------|-----------------|
| `TEACHER`      | 40          | 40              |
| `STUDENT`      | 20          | 20              |
| anything else  | 20          | 20              |

Account-wide rank and per-room rank come from the same map, so a teacher cannot
end up privileged in one and not the other.

## Names

No single endpoint returns everything, and what you get depends on the app's
entitlements:

| Endpoint                          | Carries                                  |
|-----------------------------------|------------------------------------------|
| `/schools/{id}/users`, `/users/{id}` | `pseudonym` ("Denk Kapitän"), `status`, `groups` |
| `/groups/{id}` members            | real `name` (`firstFull`/`firstCall`/`last`), `role` |
| `/people/{id}`                    | `sourceSystemIdentifier` (optional scope) |

So the import reads **each group in full**, not the school's group list alone:
that per-group call is the only place real names appear. The views are merged
by id, keeping whichever endpoint carried each field.

`displayname` prefers the real name and falls back to the pseudonym, so no
account is left showing a generated username. `realname` is set from a real name
only: a pseudonym is not a legal name and does not belong there.

Reading group detail also catches directory users that appear in a group's
member list and are absent from `/users`, which reading `/users` alone loses.

## Identity

**Eduplaces exposes no email address, and its users do not share one.** The
identifier is the Eduplaces person UUID:

| Column        | Holds                                     | Set when               |
|---------------|-------------------------------------------|------------------------|
| `idp_user_id` | Eduplaces person UUID                     | import, or first login |
| `sso_sub`     | Keycloak subject (Keycloak mints its own) | first login            |

`idp_user_id IS NOT NULL` means the row came from the directory; `sso_sub IS NOT
NULL` means it has completed an SSO login. An imported row has no email and no
password, so password login cannot work against it.

When an imported user signs in, `adoptDirectoryProvisionedUser()` matches on
`idp_user_id` and claims the existing row, creating **no account**. It touches
rows with no `sso_sub` only; a row already bound to a Keycloak subject goes
through the password-proof linking flow described in
[sso-architecture.md](sso-architecture.md).

## Webhooks

Webhooks handle drift after the import. Eduplaces reports changes only and never
replays a roster, so webhooks cannot populate a school on their own.

`POST /api/v2/webhooks/idp/{provider}`, headers `X-EP-Event-Type`
(`person`/`group`/`school`) and `X-EP-Signature-Sha256` (HMAC-SHA256 of the raw
body). Payloads carry an id and the names of changed properties, never values,
so each event is followed by a read-back against the IDM API.

| Code | When                          | Retry worth it?           |
|------|-------------------------------|---------------------------|
| 401  | Missing or bad signature      | Only if the secret rotates |
| 500  | No webhook secret configured  | Yes, once configured      |
| 422  | Uninterpretable envelope      | No                        |
| 202  | Accepted and queued           | n/a                       |

Eduplaces retries three times on non-2xx. Acknowledging at 202 and working on
the queue moves the retries to aula (`tries = 5`, backoff 10s/1m/5m/15m).

### What each event does

**person**: `create` and `update` converge the row through
`SchoolImport::importUser()`, the same call the import makes, so both paths
write identical rows including room membership and roles. `delete` sets
`status = 3` and leaves every directory-sourced room. `restore` sets
`status = 1`.

**group**: `create` and `update` upsert the room and reconcile its members.
`delete` archives the room and keeps the membership rows so a restore is clean.
A member with no local row is skipped rather than created, since that member's
own person event creates it.

**school**: updates `tenants.name`. Address, location, official id and schooling
level are read and logged but not stored. `delete` is **not** honoured: dropping
a tenant destroys a school's whole database.

### Resolving the tenant

School events map straight onto `tenants.idp_school_id`. Person and group
payloads carry no school identifier, so `TenantResolver` looks the id up in the
central `idp_directory` table and, on a miss, scans that provider's tenants
while indexing every id it sees. The import populates the index up front, so in
practice the scan runs only for entities created after onboarding. An
unresolvable id is cached for an hour, so a school this installation does not
host cannot start a scan per event.

## What is never touched

- Rooms without an `idp_group_id`: the school room and anything created inside
  aula.
- Users without an `idp_user_id` are never unenrolled by a sync.
- Admin-level accounts are never demoted by role mapping.
- Nothing is hard-deleted; legacy tables reference user rows.

## Replays and ordering

Eduplaces documents neither an idempotency key nor an ordering guarantee, so
every sync and the import are **convergent**: they read current state and make
the local rows match. Applying the same event twice changes nothing the second
time, and a failed import can simply be re-run.

## Configuration

```bash
EDUPLACES_AUTH_URL=https://auth.eduplaces.io
EDUPLACES_API_URL=https://api.eduplaces.io
EDUPLACES_CLIENT_ID=
EDUPLACES_CLIENT_SECRET=
EDUPLACES_WEBHOOK_SECRET=
```

Sandbox: `https://auth.sandbox.eduplaces.dev`, `https://api.sandbox.eduplaces.dev`.

Scopes: `urn:eduplaces:idm:v1:{schools,groups,users}:read` are required.
`people:read` is **optional**: over `/users` it adds `sourceSystemIdentifier`
alone, which nothing here reads, so a school imports completely without it and a
refusal is logged and stepped over.

Request only the scopes the Eduplaces app holds, through `EDUPLACES_IDM_SCOPES`.
The token endpoint rejects the **whole** request with `invalid_scope` when one
is ungranted.

Optional: `EDUPLACES_USERLEVEL_TEACHER` (40), `EDUPLACES_USERLEVEL_STUDENT`
(20), `EDUPLACES_USERLEVEL_DEFAULT` (20).

An Eduplaces tenant is one whose `sso_provider` matches the IdP alias
(`Tenant::usesIdpDirectory()`). `idp_school_id` is filled in by the first
login, not by configuration.

Without `EDUPLACES_WEBHOOK_SECRET` the webhook endpoint fails closed with a 500.

## Operating

`idp_webhook_events` is the audit trail: `pending`, `processed`, `skipped` with
a reason, or `failed` with the last exception.

Common skip reasons: `tenant_unresolved` (a school this installation does not
host, which is expected), `user_not_local` / `room_not_local` (a delete for
something never synced), `*_not_found_upstream` (deleted between the event and
the read-back), `school_delete_needs_operator`.

A failed import leaves `idp_import_status = failed` and the error on the
tenant. Re-running the import converges, so recovery is a re-run once the cause
is fixed.

## Known limitations

- **No email.** An imported row carries no address, so anything that mails users
  cannot reach it.
- **`schooling_level`** appears in the webhook property list and not in the
  documented school schema. Both spellings are read; neither has been observed.
