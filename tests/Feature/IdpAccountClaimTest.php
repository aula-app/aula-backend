<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\LegacyJwtService;
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
 * What happens to somebody the review could not match.
 *
 * Their provider identity has an empty row waiting for it while their real
 * account, with everything they have written, sits unlinked. Adoption would
 * hand them the empty one, so mid-migration they are asked first.
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

    public function test_it_asks_before_handing_over_an_imported_row(): void
    {
        $this->seedShell('p1');

        $response = $this->signIn('kc-sub-1', 'p1');

        // No session yet: the person has to say who they are first.
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('sso_error=account_link_required', $location);
        $this->assertStringNotContainsString('/oauth-login/', $location);
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
            // The empty row the import made has served its purpose.
            $this->assertNull(LegacyUser::find($shellId));
        });
    }

    public function test_declaring_yourself_new_completes_the_login(): void
    {
        $shellId = $this->seedShell('p1');

        $token = $this->claimToken($this->signIn('kc-sub-1', 'p1'));

        // No aula credentials to offer, so the one-shot token is the only
        // authentication available — without this a new pupil loops forever.
        $response = $this->postJson('/api/v2/auth/sso/link/decline', ['sso_link_token' => $token], [
            'aula-instance-code' => 'TEST001',
        ]);

        $response->assertOk()->assertJsonStructure(['JWT']);

        self::$testTenant->run(function () use ($shellId) {
            $this->assertSame('kc-sub-1', LegacyUser::find($shellId)->sso_sub);
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

        // Rewrite the intent to point at an identity a real account holds.
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

        // Greenfield: the imported row is theirs, no question needed.
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

    private function seedShell(string $personId): int
    {
        return (int) self::$testTenant->run(function () use ($personId) {
            $shell = new LegacyUser;
            $shell->username = 'claim_shell_'.$personId;
            $shell->displayname = 'Imported '.$personId;
            $shell->idp_user_id = $personId;
            $shell->status = LegacyUser::STATUS_ACTIVE;
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
            $user->status = LegacyUser::STATUS_ACTIVE;
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
            $ids = LegacyUser::where('username', 'like', 'claim_%')->pluck('id')->all();

            if ($ids !== []) {
                DB::table('au_rel_rooms_users')->whereIn('user_id', $ids)->delete();
                LegacyUser::whereIn('id', $ids)->delete();
            }
        });
    }
}
