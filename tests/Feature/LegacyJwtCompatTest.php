<?php

namespace Tests\Feature;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\LegacyUser;
use Laravel\Passport\Passport;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * The api_compat guard, which takes either a Passport token or the HS512 JWT
 * BE.v1 issues.
 *
 * Transitional: delete with the guard once the frontend obtains its tokens
 * from /api/v2/oauth/token.
 */
class LegacyJwtCompatTest extends TestCase
{
    use CreatesTestTenant;

    private const COMPAT_ROUTE = '/api/v2/auth/idp/import-status';

    private const PASSPORT_ONLY_ROUTE = '/api/v2/users';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
    }

    protected function tearDown(): void
    {
        self::$testTenant?->run(function () {
            LegacyUser::where('username', 'like', 'compat_%')->delete();
        });

        parent::tearDown();
    }

    public function test_a_legacy_v1_token_authenticates(): void
    {
        $user = $this->makeUser('compat_admin', UserLevel::Admin);

        $this->getJson(self::COMPAT_ROUTE, $this->headers($this->legacyJwt($user->hash_id)))
            ->assertOk();
    }

    public function test_a_passport_token_still_authenticates(): void
    {
        Passport::actingAs($this->makeUser('compat_passport', UserLevel::Admin));

        $this->getJson(self::COMPAT_ROUTE, ['aula-instance-code' => 'TEST001'])
            ->assertOk();
    }

    public function test_a_legacy_token_does_not_reach_a_passport_only_route(): void
    {
        $user = $this->makeUser('compat_scoped', UserLevel::Admin);

        $this->getJson(self::PASSPORT_ONLY_ROUTE, $this->headers($this->legacyJwt($user->hash_id)))
            ->assertUnauthorized();
    }

    /**
     * The key is per tenant, so a token minted for one must not open another.
     */
    public function test_a_token_signed_with_another_key_is_refused(): void
    {
        $user = $this->makeUser('compat_crosstenant', UserLevel::Admin);

        $this->getJson(self::COMPAT_ROUTE, $this->headers(
            $this->legacyJwt($user->hash_id, key: 'a-different-tenants-jwt-key-000000000')
        ))->assertUnauthorized();
    }

    /**
     * Eloquent turns `= null` into `IS NULL` and hash_id is nullable, so a
     * blank claim would otherwise authenticate as an arbitrary hash-less row.
     */
    public function test_a_blank_user_hash_is_refused(): void
    {
        foreach (['', null] as $hash) {
            $this->getJson(self::COMPAT_ROUTE, $this->headers($this->legacyJwt($hash)))
                ->assertUnauthorized();
        }
    }

    public function test_a_tampered_or_expired_token_is_refused(): void
    {
        $user = $this->makeUser('compat_tampered', UserLevel::Admin);

        $tampered = substr($this->legacyJwt($user->hash_id), 0, -4).'AAAA';
        $this->getJson(self::COMPAT_ROUTE, $this->headers($tampered))->assertUnauthorized();

        $expired = $this->legacyJwt($user->hash_id, exp: time() - 3600);
        $this->getJson(self::COMPAT_ROUTE, $this->headers($expired))->assertUnauthorized();
    }

    /**
     * The algorithm is pinned rather than read from the token.
     */
    public function test_a_token_declaring_another_algorithm_is_refused(): void
    {
        $user = $this->makeUser('compat_alg', UserLevel::Admin);

        $this->getJson(self::COMPAT_ROUTE, $this->headers(
            $this->legacyJwt($user->hash_id, alg: 'HS256')
        ))->assertUnauthorized();
    }

    public function test_an_inactive_user_is_refused(): void
    {
        $user = $this->makeUser('compat_inactive', UserLevel::Admin, UserStatus::Suspended);

        $this->getJson(self::COMPAT_ROUTE, $this->headers($this->legacyJwt($user->hash_id)))
            ->assertUnauthorized();
    }

    /**
     * Authorization reads the database, so a claim cannot grant a level the
     * row does not have.
     */
    public function test_a_forged_user_level_claim_does_not_escalate(): void
    {
        $user = $this->makeUser('compat_lowly', UserLevel::User);

        $this->getJson('/api/v2/auth/idp/connect', $this->headers(
            $this->legacyJwt($user->hash_id, userLevel: UserLevel::TechAdmin->value)
        ))->assertForbidden()->assertJsonPath('error', 'admin_required');
    }

    private function makeUser(string $username, UserLevel $level, UserStatus $status = UserStatus::Active): LegacyUser
    {
        return self::$testTenant->run(function () use ($username, $level, $status) {
            LegacyUser::where('username', $username)->delete();

            $user = new LegacyUser;
            $user->username = $username;
            $user->displayname = $username;
            $user->realname = $username;
            $user->pw = password_hash('irrelevant', PASSWORD_DEFAULT);
            $user->status = $status;
            $user->userlevel = $level;
            $user->hash_id = $username.'_'.substr(md5(uniqid()), 0, 12);
            $user->roles = json_encode([]);
            $user->refresh_token = false;
            $user->save();

            return $user;
        });
    }

    /**
     * A token shaped exactly like the one BE.v1 hands the frontend.
     */
    private function legacyJwt(
        ?string $userHash,
        ?string $key = null,
        int $exp = 0,
        string $alg = 'HS512',
        int $userLevel = 50,
    ): string {
        $key ??= self::$testTenant->jwt_key;

        $header = $this->base64Url(json_encode(['typ' => 'JWT', 'alg' => $alg]));
        $payload = $this->base64Url(json_encode([
            'exp' => $exp,
            'user_hash' => $userHash,
            'user_level' => $userLevel,
            'roles' => [],
            'temp_pw' => false,
        ]));

        $signature = $this->base64Url(hash_hmac(
            $alg === 'HS512' ? 'SHA512' : 'SHA256',
            "{$header}.{$payload}",
            (string) $key,
            true,
        ));

        return "{$header}.{$payload}.{$signature}";
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $token): array
    {
        return [
            'aula-instance-code' => 'TEST001',
            'Authorization' => "Bearer {$token}",
        ];
    }

    private function base64Url(string $value): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($value));
    }
}
