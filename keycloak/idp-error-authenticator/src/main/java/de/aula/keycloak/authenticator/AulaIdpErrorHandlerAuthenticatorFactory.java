package de.aula.keycloak.authenticator;

import java.util.Collections;
import java.util.List;

import org.keycloak.Config;
import org.keycloak.authentication.Authenticator;
import org.keycloak.authentication.AuthenticatorFactory;
import org.keycloak.models.AuthenticationExecutionModel.Requirement;
import org.keycloak.models.KeycloakSession;
import org.keycloak.models.KeycloakSessionFactory;
import org.keycloak.provider.ProviderConfigProperty;

public class AulaIdpErrorHandlerAuthenticatorFactory implements AuthenticatorFactory {

    public static final String PROVIDER_ID = "aula-idp-error-handler";

    private static final AulaIdpErrorHandlerAuthenticator SINGLETON = new AulaIdpErrorHandlerAuthenticator();

    private static final Requirement[] REQUIREMENT_CHOICES = {
            Requirement.REQUIRED,
            Requirement.ALTERNATIVE,
            Requirement.DISABLED,
    };

    @Override
    public String getId() {
        return PROVIDER_ID;
    }

    @Override
    public Authenticator create(KeycloakSession session) {
        return SINGLETON;
    }

    @Override
    public String getDisplayType() {
        return "Aula: Forward IdP Error to Client";
    }

    @Override
    public String getReferenceCategory() {
        return "Brokering";
    }

    @Override
    public boolean isConfigurable() {
        return false;
    }

    @Override
    public Requirement[] getRequirementChoices() {
        return REQUIREMENT_CHOICES;
    }

    @Override
    public boolean isUserSetupAllowed() {
        return false;
    }

    @Override
    public String getHelpText() {
        return "Place immediately after the Identity Provider Redirector. When a brokered IdP returns "
                + "an error (e.g. access_denied when the user cancels), sends an OAuth error=access_denied "
                + "redirect back to the client's redirect_uri instead of showing a Keycloak error page.";
    }

    @Override
    public List<ProviderConfigProperty> getConfigProperties() {
        return Collections.emptyList();
    }

    @Override
    public void init(Config.Scope config) {
    }

    @Override
    public void postInit(KeycloakSessionFactory factory) {
    }

    @Override
    public void close() {
    }
}
