<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\LegacyUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\OAuth2\User as SocialiteOAuth2User;
use Tests\Concerns\CreatesTestTenant;
use Tests\Support\SignsIdTokens;
use Tests\TestCase;

/**
 * Covers the IdP-initiated (OIDC third-party initiated login) entry point
 * that Eduplaces' marketplace launcher hits. The callback resolves the
 * aula tenant from the upstream id_token's `school` claim and maps it to
 * `tenants.idp_school_id`.
 */
class SsoIdpInitiatedTest extends TestCase
{
    use CreatesTestTenant;
    use SignsIdTokens;

    private const INSTANCE_CODE = 'TEST001';

    private const KEYCLOAK_BASE = 'https://sso.test.local';

    private const KEYCLOAK_REALM = 'aula-test';

    private const KEYCLOAK_CLIENT_ID = 'aula-backend-test';

    private const EDUPLACES_AUTH = 'https://auth.sandbox.eduplaces.dev';

    private const EDUPLACES_IDP_ALIAS = 'eduplaces';

    private const EDUPLACES_SCHOOL = 'school-uuid-aaa';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        self::$testTenant->update([
            'sso_enabled' => true,
            'sso_provider' => self::EDUPLACES_IDP_ALIAS,
            'sso_force_logout' => false,
            'sso_require_email_verified' => false,
            'idp_school_id' => self::EDUPLACES_SCHOOL,
        ]);

        config([
            'services.keycloak.base_url' => self::KEYCLOAK_BASE,
            'services.keycloak.realms' => self::KEYCLOAK_REALM,
            'services.keycloak.client_id' => self::KEYCLOAK_CLIENT_ID,
            'services.eduplaces.idp_alias' => self::EDUPLACES_IDP_ALIAS,
            'services.eduplaces.allowed_issuers' => [self::EDUPLACES_AUTH, 'https://auth.eduplaces.io'],
        ]);

        Cache::flush();
        self::$testTenant->run(fn () => Cache::flush());
        $this->fakeJwksEndpoint();
    }

    protected function tearDown(): void
    {
        self::$testTenant->run(function () {
            LegacyUser::where('email', 'like', 'idp_%@test.example')->delete();
            // Webhook-provisioned rows carry no email, so they need clearing by
            // the identifier that does exist.
            LegacyUser::where('idp_user_id', 'like', 'eduplaces-person-%')->delete();
        });
        parent::tearDown();
    }

    // =========================================================
    // /sso/idp-initiated
    // =========================================================

    public function test_idp_initiated_rejects_missing_iss(): void
    {
        $response = $this->getJson('/api/v2/auth/sso/idp-initiated');

        $response->assertStatus(400)->assertJson(['error' => 'invalid_issuer']);
    }

    public function test_idp_initiated_rejects_disallowed_iss(): void
    {
        $response = $this->getJson('/api/v2/auth/sso/idp-initiated?iss=https://attacker.example');

        $response->assertStatus(400)->assertJson(['error' => 'invalid_issuer']);
    }

    public function test_idp_initiated_redirects_to_keycloak_with_idp_hint_and_login_hint(): void
    {
        $capturedParams = [];

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('with')->andReturnUsing(function (array $params) use (&$capturedParams, $provider) {
            $capturedParams = $params;

            return $provider;
        });
        $provider->shouldReceive('redirect')->andReturn(new RedirectResponse('https://sso.test.local/realms/aula-test/protocol/openid-connect/auth'));

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);

        $hint = 'opaque-eduplaces-hint';

        $response = $this->get('/api/v2/auth/sso/idp-initiated?iss='.urlencode(self::EDUPLACES_AUTH).'&login_hint='.urlencode($hint));

        $response->assertRedirect();
        $this->assertEquals(self::EDUPLACES_IDP_ALIAS, $capturedParams['kc_idp_hint']);
        $this->assertEquals($hint, $capturedParams['login_hint']);
        $this->assertNotEmpty($capturedParams['state']);
    }

    public function test_idp_initiated_omits_login_hint_when_absent(): void
    {
        $capturedParams = [];

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('with')->andReturnUsing(function (array $params) use (&$capturedParams, $provider) {
            $capturedParams = $params;

            return $provider;
        });
        $provider->shouldReceive('redirect')->andReturn(new RedirectResponse('https://sso.test.local/x'));

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);

        $this->get('/api/v2/auth/sso/idp-initiated?iss='.urlencode(self::EDUPLACES_AUTH));

        $this->assertArrayNotHasKey('login_hint', $capturedParams);
    }

    // =========================================================
    // callback — idp-initiated branch
    // =========================================================

    public function test_callback_resolves_tenant_via_school_claim_and_authenticates(): void
    {
        $this->fakeBrokerUpstreamIdToken(['sub' => 'eduplaces-sub-aaa', 'school' => self::EDUPLACES_SCHOOL, 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-001', 'idp_resolved@test.example', 'IdP Resolved', 'idpresolved');

        $state = $this->buildIdpInitiatedState();
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('/oauth-login/', $response->headers->get('Location'));

        self::$testTenant->run(function () {
            $user = LegacyUser::where('sso_sub', 'keycloak-sub-001')->first();
            $this->assertNotNull($user, 'user should have been provisioned in the resolved tenant');
        });
    }

    public function test_callback_rejects_when_school_claim_is_missing(): void
    {
        $this->fakeBrokerUpstreamIdToken(['sub' => 'eduplaces-sub-nos', 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-noschool', 'idp_noschool@test.example', 'N', 'n');

        $state = $this->buildIdpInitiatedState();
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=idp_school_missing', $response->headers->get('Location'));
    }

    public function test_callback_rejects_when_no_aula_tenant_matches_school(): void
    {
        $this->fakeBrokerUpstreamIdToken(['sub' => 'eduplaces-sub-stray', 'school' => 'school-uuid-unknown', 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-stray', 'idp_stray@test.example', 'S', 's');

        $state = $this->buildIdpInitiatedState();
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=school_not_provisioned', $response->headers->get('Location'));
    }

    public function test_callback_rejects_when_resolved_tenant_has_sso_disabled(): void
    {
        self::$testTenant->update(['sso_enabled' => false]);

        $this->fakeBrokerUpstreamIdToken(['sub' => 'eduplaces-sub-off', 'school' => self::EDUPLACES_SCHOOL, 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-disabled', 'idp_disabled@test.example', 'D', 'd');

        $state = $this->buildIdpInitiatedState();
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=sso_disabled', $response->headers->get('Location'));
    }

    // =========================================================
    // idp_user_id stamping
    // =========================================================

    public function test_callback_records_the_idp_user_id(): void
    {
        $this->fakeBrokerUpstreamIdToken(['sub' => 'eduplaces-person-77', 'school' => self::EDUPLACES_SCHOOL, 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-person77', 'idp_person77@test.example', 'Person 77', 'person77');

        $state = $this->buildIdpInitiatedState();
        $this->get("/api/v2/auth/sso/callback?state={$state}")->assertRedirect();

        self::$testTenant->run(function () {
            $user = LegacyUser::where('sso_sub', 'keycloak-sub-person77')->first();

            $this->assertNotNull($user);
            // sso_sub stays the Keycloak subject; the Eduplaces person id is
            // what webhooks reference and lives in its own column.
            $this->assertSame('keycloak-sub-person77', $user->sso_sub);
            $this->assertSame('eduplaces-person-77', $user->idp_user_id);
        });
    }

    public function test_callback_refreshes_a_stale_idp_user_id(): void
    {
        self::$testTenant->run(function () {
            $user = LegacyUser::fromSocialiteUser($this->stubSocialiteUser('keycloak-sub-stale', 'idp_stale@test.example'));
            $user->idp_user_id = 'eduplaces-person-old';
            $user->save();
        });

        $this->fakeBrokerUpstreamIdToken(['sub' => 'eduplaces-person-new', 'school' => self::EDUPLACES_SCHOOL, 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-stale', 'idp_stale@test.example', 'Stale', 'stale');

        $state = $this->buildIdpInitiatedState();
        $this->get("/api/v2/auth/sso/callback?state={$state}")->assertRedirect();

        self::$testTenant->run(function () {
            $user = LegacyUser::where('sso_sub', 'keycloak-sub-stale')->first();
            $this->assertSame('eduplaces-person-new', $user->idp_user_id);
        });
    }

    public function test_callback_leaves_the_person_id_alone_when_another_user_holds_it(): void
    {
        self::$testTenant->run(function () {
            $squatter = LegacyUser::fromSocialiteUser($this->stubSocialiteUser('keycloak-sub-squatter', 'idp_squatter@test.example'));
            $squatter->idp_user_id = 'eduplaces-person-contested';
            $squatter->save();
        });

        $this->fakeBrokerUpstreamIdToken(['sub' => 'eduplaces-person-contested', 'school' => self::EDUPLACES_SCHOOL, 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-contender', 'idp_contender@test.example', 'Contender', 'contender');

        $state = $this->buildIdpInitiatedState();

        // The login itself must still succeed: a contested person id is an
        // operational problem to triage, not a reason to lock the user out.
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('/oauth-login/', $response->headers->get('Location'));

        self::$testTenant->run(function () {
            $contender = LegacyUser::where('sso_sub', 'keycloak-sub-contender')->first();
            $squatter = LegacyUser::where('sso_sub', 'keycloak-sub-squatter')->first();

            $this->assertNull($contender->idp_user_id);
            $this->assertSame('eduplaces-person-contested', $squatter->idp_user_id);
        });
    }

    public function test_callback_survives_an_upstream_token_without_a_sub(): void
    {
        $this->fakeBrokerUpstreamIdToken(['school' => self::EDUPLACES_SCHOOL, 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-nosub', 'idp_nosub@test.example', 'No Sub', 'nosub');

        $state = $this->buildIdpInitiatedState();
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('/oauth-login/', $response->headers->get('Location'));

        self::$testTenant->run(function () {
            $user = LegacyUser::where('sso_sub', 'keycloak-sub-nosub')->first();

            $this->assertNotNull($user);
            $this->assertNull($user->idp_user_id);
        });
    }

    // =========================================================
    // Adopting webhook-provisioned accounts
    // =========================================================

    public function test_callback_adopts_a_webhook_provisioned_account(): void
    {
        // What an IDM person webhook leaves behind: an Eduplaces person id and
        // nothing else to identify the row by.
        $seededId = $this->seedDirectoryProvisionedUser('eduplaces-person-pre');

        $this->fakeBrokerUpstreamIdToken(['sub' => 'eduplaces-person-pre', 'school' => self::EDUPLACES_SCHOOL, 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-adopt', 'idp_adopt@test.example', 'Adopted', 'adopted');

        $state = $this->buildIdpInitiatedState();
        $this->get("/api/v2/auth/sso/callback?state={$state}")->assertRedirect();

        self::$testTenant->run(function () use ($seededId) {
            $rows = LegacyUser::where('idp_user_id', 'eduplaces-person-pre')->get();

            $this->assertCount(1, $rows, 'the login must not have created a second row');
            $this->assertSame($seededId, $rows[0]->id, 'the pre-provisioned row should have been claimed');
            $this->assertSame('keycloak-sub-adopt', $rows[0]->sso_sub);
        });
    }

    public function test_adoption_matches_on_person_id_rather_than_email(): void
    {
        // The seeded row has no email at all, and the id_token carries one that
        // matches nothing. Adoption still has to happen: the Eduplaces person
        // id is the identifier, not the address.
        $seededId = $this->seedDirectoryProvisionedUser('eduplaces-person-noemail');

        $this->fakeBrokerUpstreamIdToken(['sub' => 'eduplaces-person-noemail', 'school' => self::EDUPLACES_SCHOOL, 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-noemail', 'idp_unrelated@test.example', 'No Email', 'noemail');

        $state = $this->buildIdpInitiatedState();
        $this->get("/api/v2/auth/sso/callback?state={$state}")->assertRedirect();

        self::$testTenant->run(function () use ($seededId) {
            $user = LegacyUser::find($seededId);

            $this->assertSame('keycloak-sub-noemail', $user->sso_sub);
            $this->assertSame(1, LegacyUser::where('sso_sub', 'keycloak-sub-noemail')->count());
        });
    }

    public function test_adopts_an_account_that_has_a_password(): void
    {
        // A password used to disqualify a row from adoption, on the reasoning
        // that a credentialed account is one somebody could have been using.
        // A merged account is exactly that and carries the identity anyway,
        // because a migrating school's admin confirmed the two are the same
        // person. Refusing it stranded them: the login would neither use the
        // account nor let it go, and made a duplicate instead.
        $seededId = $this->seedDirectoryProvisionedUser('eduplaces-person-haspw', password: 'a-real-password-hash');

        $this->fakeBrokerUpstreamIdToken(['sub' => 'eduplaces-person-haspw', 'school' => self::EDUPLACES_SCHOOL, 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-haspw', 'idp_haspw@test.example', 'Has Pw', 'haspw');

        $state = $this->buildIdpInitiatedState();
        $this->get("/api/v2/auth/sso/callback?state={$state}")->assertRedirect();

        self::$testTenant->run(function () use ($seededId) {
            $user = LegacyUser::find($seededId);

            $this->assertSame('keycloak-sub-haspw', $user->sso_sub);
            // Their password is left alone: linking an identity is not a reason
            // to take away the way they have always signed in.
            $this->assertNotEmpty($user->pw);
            $this->assertSame(1, LegacyUser::where('idp_user_id', 'eduplaces-person-haspw')->count());
        });
    }

    public function test_does_not_adopt_an_account_already_bound_to_another_sub(): void
    {
        $seededId = $this->seedDirectoryProvisionedUser('eduplaces-person-bound', ssoSub: 'keycloak-sub-incumbent');

        $this->fakeBrokerUpstreamIdToken(['sub' => 'eduplaces-person-bound', 'school' => self::EDUPLACES_SCHOOL, 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-usurper', 'idp_usurper@test.example', 'Usurper', 'usurper');

        $state = $this->buildIdpInitiatedState();
        $this->get("/api/v2/auth/sso/callback?state={$state}")->assertRedirect();

        self::$testTenant->run(function () use ($seededId) {
            $this->assertSame('keycloak-sub-incumbent', LegacyUser::find($seededId)->sso_sub);
        });
    }

    public function test_does_not_adopt_across_a_person_id_mismatch(): void
    {
        // Someone else's pre-provisioned row must not be handed to this login.
        $seededId = $this->seedDirectoryProvisionedUser('eduplaces-person-someone-else');

        $this->fakeBrokerUpstreamIdToken(['sub' => 'eduplaces-person-me', 'school' => self::EDUPLACES_SCHOOL, 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-mine', 'idp_mine@test.example', 'Mine', 'mine');

        $state = $this->buildIdpInitiatedState();
        $this->get("/api/v2/auth/sso/callback?state={$state}")->assertRedirect();

        self::$testTenant->run(function () use ($seededId) {
            $this->assertNull(LegacyUser::find($seededId)->sso_sub);
            // A fresh row for the person who actually logged in.
            $this->assertSame('eduplaces-person-me', LegacyUser::where('sso_sub', 'keycloak-sub-mine')->firstOrFail()->idp_user_id);
        });
    }

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * Seed the kind of row a `person` webhook leaves behind: an Eduplaces person
     * id, a derived username, and no email, password or sso_sub.
     *
     * @return int the au_users_basedata row id
     */
    private function seedDirectoryProvisionedUser(string $personId, ?string $password = null, ?string $ssoSub = null): int
    {
        return (int) self::$testTenant->run(function () use ($personId, $password, $ssoSub) {
            $user = new LegacyUser();
            $user->idp_user_id = $personId;
            $user->username = 'pre.'.substr(md5($personId), 0, 8);
            $user->displayname = 'Pre Provisioned';
            $user->email = null;
            $user->pw = $password;
            $user->sso_sub = $ssoSub;
            $user->userlevel = 20;
            $user->status = UserStatus::Active;
            $user->hash_id = md5($personId.microtime(true));
            $user->save();

            return $user->id;
        });
    }

    /**
     * Minimal Socialite user for seeding rows via LegacyUser::fromSocialiteUser().
     */
    private function stubSocialiteUser(string $sub, string $email): User
    {
        $user = \Mockery::mock(User::class);
        $user->shouldReceive('getId')->andReturn($sub);
        $user->shouldReceive('getEmail')->andReturn($email);
        $user->shouldReceive('getName')->andReturn($email);
        $user->shouldReceive('getNickname')->andReturn($email);

        return $user;
    }

    private function fakeBrokerUpstreamIdToken(array $claims): void
    {
        Http::fake([
            self::KEYCLOAK_BASE.'/realms/'.self::KEYCLOAK_REALM.'/broker/'.self::EDUPLACES_IDP_ALIAS.'/token' => Http::response([
                'id_token' => $this->makeUnverifiedJwt($claims),
            ], 200),
        ]);
    }

    private function buildIdpInitiatedState(): string
    {
        $payload = base64_encode((string) json_encode([
            'instance_code' => '__IDP_INITIATED_EDUPLACES__',
            'nonce' => 'testnonce',
        ]));
        $signature = hash_hmac('sha256', $payload, (string) config('app.key'));

        return $payload.'.'.$signature;
    }

    /**
     * Produce a JWT-shaped string whose payload section is the supplied claims.
     * The controller's decodeIdTokenPayload() only base64-decodes; it does not
     * verify a signature on the upstream broker token (Keycloak is the trust
     * boundary), so a stub header/signature is fine.
     */
    private function makeUnverifiedJwt(array $claims): string
    {
        $header = rtrim(strtr(base64_encode((string) json_encode(['alg' => 'RS256'])), '+/', '-_'), '=');
        $body = rtrim(strtr(base64_encode((string) json_encode($claims)), '+/', '-_'), '=');

        return "{$header}.{$body}.sig";
    }

    private function mockSocialiteCallback(string $keycloakSub, string $email, string $name, string $nickname): void
    {
        $idToken = $this->signIdToken([
            'iss' => self::KEYCLOAK_BASE.'/realms/'.self::KEYCLOAK_REALM,
            'aud' => self::KEYCLOAK_CLIENT_ID,
            'azp' => self::KEYCLOAK_CLIENT_ID,
            'iat' => time() - 30,
            'exp' => time() + 600,
            'sub' => $keycloakSub,
            'email' => $email,
            'email_verified' => true,
        ]);

        $socialiteUser = \Mockery::mock(SocialiteOAuth2User::class);
        $socialiteUser->token = 'kc-access-token';
        $socialiteUser->refreshToken = 'kc-refresh-token';
        $socialiteUser->accessTokenResponseBody = ['id_token' => $idToken];
        $socialiteUser->shouldReceive('getId')->andReturn($keycloakSub);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn($name);
        $socialiteUser->shouldReceive('getNickname')->andReturn($nickname);

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);
    }
}
