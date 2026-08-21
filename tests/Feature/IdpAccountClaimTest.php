<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\LegacyJwtService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\OAuth2\User;
use Tests\Concerns\CreatesTestTenant;
use Tests\Support\SignsIdTokens;
use Tests\TestCase;

/**
 * Covers offerAccountClaim(): an SSO login on a migrating tenant whose
 * sso_sub matches no row and whom MergeProposalBuilder left unmatched.
 *
 * Two rows could be theirs: one the directory import created, carrying
 * idp_user_id and no password, and one pre-existing local account. The user
 * chooses instead of being bound to the imported row.
 */
class IdpAccountClaimTest extends TestCase
{
    use CreatesTestTenant;
    use SignsIdTokens;

    private const string KEYCLOAK_BASE = 'https://sso.test.local';

    private const string KEYCLOAK_REALM = 'aula-test';

    private const string KEYCLOAK_CLIENT_ID = 'aula-backend-test';

    private const string SCHOOL = 'school-claim-test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        self::$testTenant->refresh();
        self::$testTenant->update([
            'sso_enabled' => true,
            'sso_provider' => 'eduplaces',
            'sso_require_email_verified' => false,
            'idp_school_id' => self::SCHOOL,
            'idp_migration_status' => Tenant::IDP_MIGRATION_LINKING,
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

    public function test_a_merged_account_is_the_one_that_signs_in(): void
    {
        // The state MergeProposalApplier leaves: the local account keeps its
        // password and content and now carries the confirmed idp_user_id.
        $realId = $this->seedRealUser('claim_merged');
        self::$testTenant->run(
            fn () => LegacyUser::where('id', $realId)->update(['idp_user_id' => 'p1']),
        );

        $response = $this->signIn('kc-sub-1', 'p1');

        self::$testTenant->run(function () use ($realId) {
            $merged = LegacyUser::find($realId);

            // idp_user_id is the admin's assertion of ownership, so
            // adoptDirectoryProvisionedUser() signs this account in.
            $this->assertSame('kc-sub-1', $merged->sso_sub, 'the merged account should be the one signed in');

            // And no second row was created for the same idp_user_id.
            $this->assertSame(
                1,
                LegacyUser::where('idp_user_id', 'p1')->count(),
                'a second row for a person who already has an account is a duplicate',
            );
        });

        $this->assertStringContainsString('/oauth-login/', (string) $response->headers->get('Location'));
    }

    public function test_it_asks_before_handing_over_an_imported_row(): void
    {
        $this->seedShell('p1');

        $response = $this->signIn('kc-sub-1', 'p1');

        // No JWT yet: the account has to be claimed or declined first.
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('sso_error=account_link_required', $location);
        $this->assertStringNotContainsString('/oauth-login/', $location);
    }

    public function test_it_asks_even_before_the_import_has_made_a_row(): void
    {
        // A tenant between IDP_MIGRATION_FLAGGED and an applied merge: no row
        // carries an idp_user_id and every account is still a password one.
        $realId = $this->seedRealUser('claim_pupil');

        $response = $this->signIn('kc-sub-1', 'p1');

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('sso_error=account_link_required', $location);
        $this->assertStringNotContainsString('/oauth-login/', $location);

        self::$testTenant->run(function () use ($realId) {
            // Provisioning here would be the duplicate, with the pre-existing
            // account still present.
            $this->assertSame(0, LegacyUser::where('idp_user_id', 'p1')->count());
            $this->assertNotNull(LegacyUser::find($realId));
        });
    }

    public function test_proving_a_password_moves_the_identity_to_the_real_account(): void
    {
        $shellId = $this->seedShell('p1');
        $realId = $this->seedRealUser('claim_pupil');

        $token = $this->claimToken($this->signIn('kc-sub-1', 'p1'));

        $this->postJson('/api/v2/auth/sso/link', ['sso_link_token' => $token], $this->headersFor($realId))
            ->assertOk()->assertJsonPath('success', true);

        self::$testTenant->run(function () use ($realId, $shellId) {
            $real = LegacyUser::find($realId);

            $this->assertSame('p1', $real->idp_user_id);
            $this->assertSame('kc-sub-1', $real->sso_sub);
            // The row SchoolImport created is gone.
            $this->assertNull(LegacyUser::find($shellId));
        });
    }

    public function test_declaring_yourself_new_completes_the_login(): void
    {
        $shellId = $this->seedShell('p1');

        $token = $this->claimToken($this->signIn('kc-sub-1', 'p1'));

        // No aula password to offer, so sso_link_token is the only credential
        // declineAccountClaim() can authenticate on.
        $response = $this->postJson('/api/v2/auth/sso/link/decline', ['sso_link_token' => $token], [
            'aula-instance-code' => 'TEST001',
        ]);

        $response->assertOk()->assertJsonStructure(['JWT']);

        self::$testTenant->run(function () use ($shellId) {
            $this->assertSame('kc-sub-1', LegacyUser::find($shellId)->sso_sub);
        });
    }

    public function test_declaring_yourself_new_provisions_an_account_when_nothing_was_waiting(): void
    {
        // The same window as above, with no local row holding this
        // idp_user_id and no password account to claim.
        $token = $this->claimToken($this->signIn('kc-sub-1', 'p1'));

        $this->fakeDirectoryPerson('p1', 'Neue', 'Person');

        $this->postJson('/api/v2/auth/sso/link/decline', ['sso_link_token' => $token], [
            'aula-instance-code' => 'TEST001',
        ])->assertOk()->assertJsonStructure(['JWT']);

        self::$testTenant->run(function () {
            $user = LegacyUser::where('idp_user_id', 'p1')->first();

            // Provisioned through SchoolImport::importUser(), so arriving
            // before the roster gives the same row the roster would have.
            $this->assertNotNull($user);
            $this->assertSame('kc-sub-1', $user->sso_sub);
            $this->assertSame('Neue Person', $user->displayname);
        });
    }

    public function test_a_claim_token_is_one_shot(): void
    {
        $this->seedShell('p1');
        $realId = $this->seedRealUser('claim_pupil');

        $token = $this->claimToken($this->signIn('kc-sub-1', 'p1'));

        $this->postJson('/api/v2/auth/sso/link', ['sso_link_token' => $token], $this->headersFor($realId))->assertOk();
        $this->postJson('/api/v2/auth/sso/link', ['sso_link_token' => $token], $this->headersFor($realId))
            ->assertStatus(404);
    }

    public function test_it_refuses_to_take_an_identity_off_a_real_account(): void
    {
        $ownerId = $this->seedRealUser('claim_owner');
        self::$testTenant->run(fn () => LegacyUser::where('id', $ownerId)->update(['idp_user_id' => 'p1']));

        $shellId = $this->seedShell('p2');
        $realId = $this->seedRealUser('claim_pupil');

        $token = $this->claimToken($this->signIn('kc-sub-2', 'p2'));

        // Point the intent at an idp_user_id a password account already holds.
        $intent = Cache::get("sso_link:{$token}");
        self::$testTenant->run(function () use ($token, $intent) {
            Cache::put("sso_link:{$token}", ['claimable' => true] + $intent + [], now()->addMinutes(10));
            Cache::put("sso_link:{$token}", array_merge($intent, ['idp_user_id' => 'p1']), now()->addMinutes(10));
        });

        $this->postJson('/api/v2/auth/sso/link', ['sso_link_token' => $token], $this->headersFor($realId))
            ->assertStatus(409)->assertJsonPath('error', 'idp_identity_taken');

        self::$testTenant->run(function () use ($ownerId, $shellId) {
            $this->assertSame('p1', LegacyUser::find($ownerId)->idp_user_id);
            $this->assertNotNull(LegacyUser::find($shellId));
        });
    }

    public function test_a_school_that_is_not_migrating_is_never_asked(): void
    {
        Tenant::where('id', self::$testTenant->id)->update(['idp_migration_status' => null]);
        $shellId = $this->seedShell('p1');

        $response = $this->signIn('kc-sub-1', 'p1');

        // With idp_migration_status null, adoptDirectoryProvisionedUser() takes
        // the imported row and offerAccountClaim() never runs.
        $this->assertStringContainsString('/oauth-login/', (string) $response->headers->get('Location'));
        self::$testTenant->run(
            fn () => $this->assertSame('kc-sub-1', LegacyUser::find($shellId)->sso_sub),
        );
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function claimToken(TestResponse $response): string
    {
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        return (string) ($query['sso_link'] ?? '');
    }

    private function signIn(string $keycloakSub, string $personId): TestResponse
    {
        $header = rtrim(strtr(base64_encode((string) json_encode(['alg' => 'RS256'])), '+/', '-_'), '=');
        $body = rtrim(strtr(base64_encode((string) json_encode([
            'sub' => $personId, 'school' => self::SCHOOL,
        ])), '+/', '-_'), '=');

        Http::fake([
            '*/protocol/openid-connect/certs' => Http::response($this->jwksDocument()),
            '*/broker/eduplaces/token' => Http::response(['id_token' => "{$header}.{$body}.sig"]),
        ]);

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

        $payload = base64_encode((string) json_encode(['instance_code' => 'TEST001', 'nonce' => 'claimnonce']));
        $state = $payload.'.'.hash_hmac('sha256', $payload, (string) config('app.key'));

        return $this->get("/api/v2/auth/sso/callback?state={$state}");
    }

    /**
     * Answer the IDM lookups provisionFromDirectory() makes. Registered after
     * the login's own fake, which does not match these paths.
     */
    private function fakeDirectoryPerson(string $personId, string $first, string $last): void
    {
        $person = [
            'id' => $personId,
            'name' => ['firstFull' => $first, 'firstCall' => $first, 'last' => $last],
            'role' => 'STUDENT',
            'status' => 'ACTIVE',
            'groups' => [],
        ];

        config([
            'idp.providers.eduplaces.auth_url' => 'https://idm.test.local',
            'idp.providers.eduplaces.api_url' => 'https://idm.test.local',
            'idp.providers.eduplaces.client_id' => 'test-client',
            'idp.providers.eduplaces.client_secret' => 'test-secret',
        ]);

        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => 'idm-token', 'token_type' => 'bearer', 'expires_in' => 3599,
            ]),
            "*/people/{$personId}" => Http::response($person),
            "*/users/{$personId}" => Http::response($person),
        ]);
    }

    private function seedShell(string $personId): int
    {
        return (int) self::$testTenant->run(function () use ($personId) {
            $shell = new LegacyUser;
            $shell->username = 'claim_shell_'.$personId;
            $shell->displayname = 'Imported '.$personId;
            $shell->idp_user_id = $personId;
            $shell->status = UserStatus::Active;
            $shell->userlevel = 20;
            $shell->hash_id = md5($shell->username);
            $shell->save();

            return $shell->id;
        });
    }

    private function seedRealUser(string $username): int
    {
        return (int) self::$testTenant->run(function () use ($username) {
            LegacyUser::where('username', $username)->delete();

            $user = new LegacyUser;
            $user->username = $username;
            $user->displayname = $username;
            $user->pw = password_hash('secret', PASSWORD_BCRYPT);
            $user->status = UserStatus::Active;
            $user->userlevel = 20;
            $user->hash_id = md5($username.microtime(true));
            $user->save();

            return $user->id;
        });
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
        self::$testTenant->run(function () {
            // Matched on idp_user_id and sso_sub as well as the username
            // prefix: a row provisioned by a login is named after the directory
            // user, so the prefix alone would leave it behind to answer the
            // next test's lookup.
            $ids = LegacyUser::where('username', 'like', 'claim_%')
                ->orWhereIn('idp_user_id', ['p1', 'p2'])
                ->orWhereIn('sso_sub', ['kc-sub-1', 'kc-sub-2'])
                ->pluck('id')
                ->all();

            if ($ids !== []) {
                DB::table('au_rel_rooms_users')->whereIn('user_id', $ids)->delete();
                LegacyUser::whereIn('id', $ids)->delete();
            }
        });
    }
}
