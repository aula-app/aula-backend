package de.aula.keycloak.authenticator;

import jakarta.ws.rs.core.Response;

import org.jboss.logging.Logger;
import org.keycloak.authentication.AuthenticationFlowContext;
import org.keycloak.authentication.Authenticator;
import org.keycloak.events.Errors;
import org.keycloak.events.EventBuilder;
import org.keycloak.models.KeycloakSession;
import org.keycloak.models.RealmModel;
import org.keycloak.models.UserModel;
import org.keycloak.protocol.LoginProtocol;
import org.keycloak.sessions.AuthenticationSessionModel;

/**
 * Forwards a brokered identity provider error back to the OIDC client.
 *
 * <p>When an upstream IdP returns an error to Keycloak's broker endpoint — most commonly
 * {@code error=access_denied} because the user cancelled the login at the IdP — Keycloak's
 * default behaviour is to fall through the browser flow and dead-end on its own error page.
 * It never forwards the error to the client application (see keycloak/keycloak#17441,
 * #47955, #10250).</p>
 *
 * <p>This authenticator sits immediately after the <em>Identity Provider Redirector</em>.
 * On a normal login it is a no-op ({@link AuthenticationFlowContext#attempted()}). When it
 * detects a forwarded IdP error, it sends an OAuth {@code error=access_denied} response back
 * to the client's {@code redirect_uri} (carrying the original {@code state}), so the
 * application — not Keycloak — owns the cancel UX.</p>
 *
 * <p>The detection signal ({@link AuthenticationFlowContext#getForwardedErrorMessage()}) and
 * the redirect idiom mirror Keycloak's own {@code IdentityProviderAuthenticator} and
 * {@code AuthenticationProcessor#cancelLogin} respectively.</p>
 */
public class AulaIdpErrorHandlerAuthenticator implements Authenticator {

    private static final Logger LOG = Logger.getLogger(AulaIdpErrorHandlerAuthenticator.class);

    @Override
    public void authenticate(AuthenticationFlowContext context) {
        if (context.getForwardedErrorMessage() == null) {
            // Not an error return from a broker (normal login, or no IdP involved):
            // do nothing and let the rest of the browser flow proceed.
            context.attempted();
            return;
        }

        AuthenticationSessionModel authSession = context.getAuthenticationSession();
        KeycloakSession session = context.getSession();

        LOG.debugf("Forwarding brokered IdP error back to client '%s' as access_denied",
                authSession.getClient().getClientId());

        EventBuilder event = context.getEvent();
        event.error(Errors.REJECTED_BY_USER);

        LoginProtocol protocol = session.getProvider(LoginProtocol.class, authSession.getProtocol());
        protocol.setRealm(context.getRealm())
                .setHttpHeaders(context.getHttpRequest().getHttpHeaders())
                .setUriInfo(context.getUriInfo())
                .setEventBuilder(event);

        // CANCELLED_BY_USER maps to OIDC error=access_denied (see OIDCLoginProtocol#sendError).
        Response response = protocol.sendError(authSession, LoginProtocol.Error.CANCELLED_BY_USER, null);
        context.forceChallenge(response);
    }

    @Override
    public void action(AuthenticationFlowContext context) {
        // This authenticator never renders a form of its own, so there is no action to process.
    }

    @Override
    public boolean requiresUser() {
        return false;
    }

    @Override
    public boolean configuredFor(KeycloakSession session, RealmModel realm, UserModel user) {
        return true;
    }

    @Override
    public void setRequiredActions(KeycloakSession session, RealmModel realm, UserModel user) {
    }

    @Override
    public void close() {
    }
}
