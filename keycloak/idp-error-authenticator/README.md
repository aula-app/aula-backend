# Aula Keycloak IdP Error Authenticator

A Keycloak authenticator SPI that forwards a brokered identity-provider error back to
the OIDC client instead of dead-ending on a Keycloak error page.

## Why this exists

When an upstream IdP (e.g. Eduplaces) returns `error=access_denied` to Keycloak's broker
endpoint — most commonly because the user clicked **cancel** at the IdP — Keycloak does
**not** forward that error to the client application. Depending on the browser-flow
configuration it either shows its own login page or a "We are sorry… Access denied" error
page. This is a long-standing Keycloak limitation:

- [keycloak/keycloak#17441](https://github.com/keycloak/keycloak/issues/17441) — closed with "show an error page", not a client redirect
- [keycloak/keycloak#47955](https://github.com/keycloak/keycloak/issues/47955) — fixed the `kc_idp_hint` redirect loop (needs Keycloak ≥ 26.6.1) but only routes to the next authenticator
- [keycloak/keycloak#10250](https://github.com/keycloak/keycloak/issues/10250) — the browser flow does not resume after an IdP redirect

This authenticator closes that gap. Placed after the **Identity Provider Redirector**, it:

- is a **no-op** on a normal login (`context.attempted()`), and
- on a forwarded IdP error, sends an OAuth `error=access_denied` redirect to the client's
  `redirect_uri` (carrying the original `state`).

The aula backend already handles that: `SsoController::callback()` maps
`?error=access_denied` to a redirect to `{frontend}/login?sso_error=login_cancelled`.

## Requirements

- **Keycloak ≥ 26.6.1** (below that, the `kc_idp_hint` flow loops before this authenticator is ever reached).
- JDK 17+ and Maven to build.

## Build

```bash
mvn -f keycloak/idp-error-authenticator/pom.xml clean package
# -> target/aula-idp-error-authenticator.jar
```

Keep `keycloak.version` in `pom.xml` and the base image tag in `Dockerfile` in sync with
your running Keycloak.

## Deploy

### Option A — bake into a custom image (recommended)

```bash
mvn -f keycloak/idp-error-authenticator/pom.xml clean package
docker build -f keycloak/idp-error-authenticator/Dockerfile \
  -t aulaapp/aula-keycloak:26.5.5-aula keycloak/idp-error-authenticator
docker push aulaapp/aula-keycloak:26.5.5-aula
```

Point your Keycloak service at `aulaapp/aula-keycloak:26.5.5-aula` instead of
`quay.io/keycloak/keycloak:latest` and recreate the container. Config lives in the DB, so
nothing is lost.

### Option B — mount the jar

Mount the jar into `/opt/keycloak/providers/` and run `kc.sh build` (or restart in dev mode,
which auto-builds):

```yaml
volumes:
  - ./keycloak/idp-error-authenticator/target/aula-idp-error-authenticator.jar:/opt/keycloak/providers/aula-idp-error-authenticator.jar:ro
```

## Wire it into the browser flow (aula realm)

1. **Authentication → Flows** → duplicate **browser** (e.g. `browser-aula`) so you can revert by re-binding.
2. In the copy, add an execution right **after** *Identity Provider Redirector*:
   **Add step → "Aula: Forward IdP Error to Client"**.
3. Set that step's requirement to **Alternative**, and move it directly **below** the
   Identity Provider Redirector (and above the *Forms* subflow if present).
4. **Action → Bind flow → Browser flow**.

You can leave the *Forms* subflow enabled — the handler intercepts IdP errors before Forms
is reached, and Forms still serves any non-brokered login.

## Verify

Start an SSO login, cancel at the IdP. Expected chain:

```
IdP → …/broker/eduplaces/endpoint?error=access_denied
    → http://localhost:8080/api/v2/auth/sso/callback?error=access_denied&state=…
    → {frontend}/login?sso_error=login_cancelled
```

## How it works

`context.getForwardedErrorMessage() != null` is the same signal Keycloak's own
`IdentityProviderAuthenticator` uses to detect a returned IdP error. On that signal this
authenticator replicates `AuthenticationProcessor#cancelLogin`:
`LoginProtocol.sendError(authSession, Error.CANCELLED_BY_USER, null)` (which OIDC maps to
`access_denied`) followed by `forceChallenge(response)`.
