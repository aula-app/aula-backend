<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\LegacyJwtService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User;
use Tests\Concerns\CreatesTestTenant;
use Tests\Support\SignsIdTokens;
use Tests\TestCase;

/**
 * The first step of migrating a school that already uses aula: its admin
 * connects their own account to the identity provider.
 *
 * This both links their account and establishes which school the tenant is —
 * by proof, from the id_token, rather than by anyone choosing from a list.
 */
class IdpConnectIdentityTest extends TestCase
{
    use CreatesTestTenant;
    use SignsIdTokens;

    private const string KEYCLOAK_BASE = 'https://sso.test.local';

    private const string KEYCLOAK_REALM = 'aula-test';

    private const string KEYCLOAK_CLIENT_ID = 'aula-backend-test';

    private const string SCHOOL = 'school-connect-test';

    private const string ADMIN = 'connect_admin';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        self::$testTenant->refresh();
        self::$testTenant->update([
            'sso_enabled' => true,
            'sso_provider' => 'eduplaces',
            'sso_require_email_verified' => false,
            'idp_school_id' => null,
            'idp_migration_status' => Tenant::IDP_MIGRATION_FLAGGED,
            'admin1_username' => self::ADMIN,
        ]);

        config([
            'services.keycloak.base_url' => self::KEYCLOAK_BASE,
            'services.keycloak.realms' => self::KEYCLOAK_REALM,
            'services.keycloak.client_id' => self::KEYCLOAK_CLIENT_ID,
            'services.eduplaces.idp_alias' => 'eduplaces',
        ]);

        Cache::flush();
        self::$testTenant->run(fn () => Cache::flush());
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        Tenant::where('id', self::$testTenant->id)->update([
            'idp_school_id' => null,
            'idp_migration_status' => null,
        ]);
        parent::tearDown();
    }

    public function test_an_admin_gets_a_login_url_that_carries_their_account(): void
    {
        $this->mockRedirect();

        $response = $this->getJson('/api/v2/auth/idp/connect', $this->adminHeaders());

        $response->assertOk()->assertJsonStructure(['url']);
    }

    public function test_a_non_admin_cannot_connect_the_school(): void
    {
        $id = $this->seedUser('connect_pupil', 20);

        $response = $this->getJson('/api/v2/auth/idp/connect', $this->headersFor($id));

        // Connecting decides which school every future import belongs to.
        $response->assertStatus(403)->assertJsonPath('error', 'admin_required');
    }

    public function test_the_callback_links_the_admin_and_learns_the_school(): void
    {
        $adminId = $this->seedUser(self::ADMIN, 50);

        $this->completeConnect($adminId, 'kc-sub-admin', 'person-admin');

        // The school is now known, by proof rather than by being typed in.
        $this->assertSame(self::SCHOOL, self::$testTenant->fresh()->idp_school_id);
    }

    public function test_the_link_stamps_the_identity_on_that_admin(): void
    {
        $adminId = $this->seedUser(self::ADMIN, 50);

        $token = $this->completeConnect($adminId, 'kc-sub-admin', 'person-admin');

        $this->postJson('/api/v2/auth/sso/link', ['sso_link_token' => $token], $this->headersFor($adminId))
            ->assertOk()->assertJsonPath('success', true);

        self::$testTenant->run(function () use ($adminId) {
            $admin = LegacyUser::find($adminId);

            $this->assertSame('kc-sub-admin', $admin->sso_sub);
            $this->assertSame('person-admin', $admin->idp_user_id);
        });
    }

    public function test_connecting_advances_the_migration_state(): void
    {
        $adminId = $this->seedUser(self::ADMIN, 50);
        $token = $this->completeConnect($adminId, 'kc-sub-admin', 'person-admin');

        $this->postJson('/api/v2/auth/sso/link', ['sso_link_token' => $token], $this->headersFor($adminId))
            ->assertOk();

        // `connected` is what unlocks preparing the import.
        $this->assertSame(Tenant::IDP_MIGRATION_CONNECTED, self::$testTenant->fresh()->idp_migration_status);
    }

    public function test_it_refuses_an_identity_that_belongs_to_another_account(): void
    {
        $adminId = $this->seedUser(self::ADMIN, 50);
        self::$testTenant->run(function () {
            LegacyUser::where('username', 'connect_other')->delete();
            $other = new LegacyUser;
            $other->username = 'connect_other';
            $other->displayname = 'Other';
            $other->idp_user_id = 'person-admin';
            $other->status = LegacyUser::STATUS_ACTIVE;
            $other->userlevel = 20;
            $other->hash_id = md5('connect_other');
            $other->save();
        });

        $response = $this->connectCallback($adminId, 'kc-sub-admin', 'person-admin');

        // Two aula accounts claiming one provider identity needs a human.
        $this->assertStringContainsString('sso_error=idp_identity_taken', $response->headers->get('Location'));
    }

    public function test_a_school_another_tenant_holds_says_so(): void
    {
        $adminId = $this->seedUser(self::ADMIN, 50);
        $otherId = $this->seedRivalTenant(self::SCHOOL);

        try {
            $response = $this->connectCallback($adminId, 'kc-sub-admin', 'person-admin');

            // Reporting this as a missing school sends the operator looking for
            // a broken claim, when the real answer is the wrong instance code.
            $this->assertStringContainsString('sso_error=idp_school_taken', $response->headers->get('Location'));
            $this->assertNull(self::$testTenant->fresh()->idp_school_id);
        } finally {
            // The callback leaves tenancy initialised, so an unqualified query
            // here would look for `tenants` inside the tenant's own database
            // and leak the row into every test that follows.
            $this->central()->table('tenants')->where('id', $otherId)->delete();
        }
    }

    public function test_a_login_with_no_school_claim_still_says_missing(): void
    {
        $adminId = $this->seedUser(self::ADMIN, 50);

        $this->fakeUpstreamWithoutSchool('person-admin');
        $this->mockSocialiteUser('kc-sub-admin');

        $payload = base64_encode((string) json_encode([
            'instance_code' => 'TEST001',
            'link_user_id' => $adminId,
            'nonce' => 'connectnonce',
        ]));
        $state = $payload.'.'.hash_hmac('sha256', $payload, (string) config('app.key'));

        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $this->assertStringContainsString('sso_error=idp_school_missing', $response->headers->get('Location'));
    }

    public function test_the_connect_callback_issues_no_session(): void
    {
        $adminId = $this->seedUser(self::ADMIN, 50);

        $response = $this->connectCallback($adminId, 'kc-sub-admin', 'person-admin');

        // The admin already has one; this hop only produces a link token.
        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('/oauth-login/', $location);
        $this->assertStringContainsString('sso_link=', $location);
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function completeConnect(int $adminId, string $keycloakSub, string $personId): string
    {
        $response = $this->connectCallback($adminId, $keycloakSub, $personId);
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        return (string) ($query['sso_link'] ?? '');
    }

    private function connectCallback(int $adminId, string $keycloakSub, string $personId): TestResponse
    {
        $this->fakeUpstream($personId);
        $this->mockSocialiteUser($keycloakSub);

        $payload = base64_encode((string) json_encode([
            'instance_code' => 'TEST001',
            'link_user_id' => $adminId,
            'nonce' => 'connectnonce',
        ]));
        $state = $payload.'.'.hash_hmac('sha256', $payload, (string) config('app.key'));

        return $this->get("/api/v2/auth/sso/callback?state={$state}");
    }

    private function central(): ConnectionInterface
    {
        return DB::connection(config('tenancy.database.central_connection'));
    }

    /**
     * A central row only, inserted past the model so no database is built for
     * a tenant that exists purely to own a school id.
     */
    private function seedRivalTenant(string $schoolId): string
    {
        $id = 'rival-'.substr(md5($schoolId), 0, 8);

        $this->central()->table('tenants')->where('id', $id)->delete();
        $this->central()->table('tenants')->insert([
            'id' => $id,
            'name' => 'Rival '.$id,
            'api_base_url' => 'http://rival.test',
            'admin1_username' => 'rival_admin',
            'admin1_email' => 'rival@rival.test',
            'instance_code' => substr($id, 0, 10),
            'idp_school_id' => $schoolId,
            'data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function fakeUpstreamWithoutSchool(string $personId): void
    {
        $header = rtrim(strtr(base64_encode((string) json_encode(['alg' => 'RS256'])), '+/', '-_'), '=');
        $body = rtrim(strtr(base64_encode((string) json_encode(['sub' => $personId])), '+/', '-_'), '=');

        Http::fake([
            '*/protocol/openid-connect/certs' => Http::response($this->jwksDocument()),
            '*/broker/eduplaces/token' => Http::response(['id_token' => "{$header}.{$body}.sig"]),
        ]);
    }

    private function fakeUpstream(string $personId): void
    {
        $header = rtrim(strtr(base64_encode((string) json_encode(['alg' => 'RS256'])), '+/', '-_'), '=');
        $body = rtrim(strtr(base64_encode((string) json_encode([
            'sub' => $personId, 'school' => self::SCHOOL,
        ])), '+/', '-_'), '=');

        Http::fake([
            '*/protocol/openid-connect/certs' => Http::response($this->jwksDocument()),
            '*/broker/eduplaces/token' => Http::response(['id_token' => "{$header}.{$body}.sig"]),
        ]);
    }

    private function mockSocialiteUser(string $keycloakSub): void
    {
        $idToken = $this->signIdToken([
            'iss' => self::KEYCLOAK_BASE.'/realms/'.self::KEYCLOAK_REALM,
            'aud' => self::KEYCLOAK_CLIENT_ID,
            'azp' => self::KEYCLOAK_CLIENT_ID,
            'iat' => time() - 30,
            'exp' => time() + 600,
            'sub' => $keycloakSub,
            'email_verified' => true,
        ]);

        $user = \Mockery::mock(User::class);
        $user->token = 'kc-access-token';
        $user->refreshToken = 'kc-refresh-token';
        $user->accessTokenResponseBody = ['id_token' => $idToken];
        $user->shouldReceive('getId')->andReturn($keycloakSub);
        $user->shouldReceive('getEmail')->andReturn(null);
        $user->shouldReceive('getName')->andReturn(null);
        $user->shouldReceive('getNickname')->andReturn(null);

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($user);

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);
    }

    private function mockRedirect(): void
    {
        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('with')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(new RedirectResponse('https://sso.test.local/auth'));

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);
    }

    private function seedUser(string $username, int $level): int
    {
        return (int) self::$testTenant->run(function () use ($username, $level) {
            LegacyUser::where('username', $username)->delete();

            $user = new LegacyUser;
            $user->username = $username;
            $user->displayname = $username;
            $user->pw = password_hash('secret', PASSWORD_BCRYPT);
            $user->status = LegacyUser::STATUS_ACTIVE;
            $user->userlevel = $level;
            $user->hash_id = md5($username.microtime(true));
            $user->save();

            return $user->id;
        });
    }

    /**
     * @return array<string, string>
     */
    private function adminHeaders(): array
    {
        return $this->headersFor($this->seedUser(self::ADMIN, 50));
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(int $userId): array
    {
        $jwt = self::$testTenant->run(
            fn () => app(LegacyJwtService::class)->generateToken(LegacyUser::findOrFail($userId)),
        );

        return ['aula-instance-code' => 'TEST001', 'Authorization' => "Bearer {$jwt}"];
    }

    private function clean(): void
    {
        self::$testTenant->run(
            fn () => LegacyUser::whereIn('username', [self::ADMIN, 'connect_pupil', 'connect_other'])->delete(),
        );
    }
}
