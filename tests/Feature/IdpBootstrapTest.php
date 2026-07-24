<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ImportSchoolForTenant;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\Idp\SchoolImport;
use App\Services\LegacyJwtService;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User;
use Tests\Concerns\CreatesTestTenant;
use Tests\Support\SignsIdTokens;
use Tests\TestCase;

/**
 * The onboarding flow end to end:
 *
 *   1. a tenant is created, with one admin
 *   2. whoever holds the instance code logs in via SSO first
 *   3. that login takes over the admin account and imports the school
 *   4. everyone else logs in and finds their account already there
 */
class IdpBootstrapTest extends TestCase
{
    use CreatesTestTenant;
    use SignsIdTokens;

    private const string KEYCLOAK_BASE = 'https://sso.test.local';

    private const string KEYCLOAK_REALM = 'aula-test';

    private const string KEYCLOAK_CLIENT_ID = 'aula-backend-test';

    private const string API_URL = 'https://api.eduplaces.test';

    private const string AUTH_URL = 'https://auth.eduplaces.test';

    private const string SCHOOL = 'school-bootstrap';

    private const string ADMIN_USERNAME = 'bootstrap_admin';

    private string $currentPersonId = '';

    private string $currentKeycloakSub = '';

    private bool $socialiteMocked = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        // Shared across test classes, so the in-memory copy goes stale and
        // Eloquent would skip writes it wrongly believes are no-ops.
        self::$testTenant->refresh();
        self::$testTenant->update([
            'sso_enabled' => true,
            'sso_provider' => 'eduplaces',
            'sso_require_email_verified' => false,
            'sso_force_logout' => false,
            // Deliberately not set: the first login learns it from the
            // upstream `school` claim.
            'idp_school_id' => null,
            'idp_import_status' => null,
            'admin1_username' => self::ADMIN_USERNAME,
        ]);

        config([
            'services.keycloak.base_url' => self::KEYCLOAK_BASE,
            'services.keycloak.realms' => self::KEYCLOAK_REALM,
            'services.keycloak.client_id' => self::KEYCLOAK_CLIENT_ID,
            'services.eduplaces.idp_alias' => 'eduplaces',
            'idp.providers.eduplaces.auth_url' => self::AUTH_URL,
            'idp.providers.eduplaces.api_url' => self::API_URL,
            'idp.providers.eduplaces.client_id' => 'test-client',
            'idp.providers.eduplaces.client_secret' => 'test-secret',
            'idp.providers.eduplaces.roles' => ['TEACHER' => 40, 'STUDENT' => 20],
            'idp.providers.eduplaces.default_role' => 20,
        ]);

        Cache::flush();
        self::$testTenant->run(fn () => Cache::flush());
        $this->cleanTenant();
        $this->seedTenantAdmin();
    }

    protected function tearDown(): void
    {
        $this->cleanTenant();
        Tenant::where('id', self::$testTenant->id)->update([
            'idp_school_id' => null,
            'idp_import_status' => null,
        ]);
        parent::tearDown();
    }

    public function test_learns_the_school_id_from_the_first_login(): void
    {
        $this->assertNull(self::$testTenant->fresh()->idp_school_id, 'precondition: nothing configured by hand');

        $this->login('kc-sub-principal', 'person-teacher');

        // Nobody had to look a UUID up: the id_token said which school it was.
        $this->assertSame(self::SCHOOL, self::$testTenant->fresh()->idp_school_id);
    }

    public function test_refuses_to_take_a_school_another_tenant_already_holds(): void
    {
        $rival = Tenant::create([
            'name' => 'Rival Eduplaces IdpSchool',
            'instance_code' => 'RIVAL2',
            'api_base_url' => 'https://rival2.example',
            'admin1_username' => 'rival2_admin',
            'admin1_email' => 'rival2@example.test',
            'idp_school_id' => self::SCHOOL,
        ]);

        try {
            $this->login('kc-sub-principal', 'person-teacher');

            // One school, one tenant: the login must not move it across.
            $this->assertNull(self::$testTenant->fresh()->idp_school_id);
            $this->assertNull(self::$testTenant->fresh()->idp_import_status);
        } finally {
            $rival->delete();
        }
    }

    public function test_the_import_status_endpoint_blocks_before_the_first_login(): void
    {
        // An Eduplaces tenant with no import yet is not ready, even though its
        // school is still unknown.
        $this->seedTenantAdmin();

        $jwt = self::$testTenant->run(fn () => app(LegacyJwtService::class)->generateToken(
            LegacyUser::where('username', self::ADMIN_USERNAME)->firstOrFail(),
        ));

        $this->getJson('/api/v2/auth/idp/import-status', [
            'aula-instance-code' => 'TEST001',
            'Authorization' => "Bearer {$jwt}",
        ])->assertOk()->assertJsonPath('ready', false)->assertJsonPath('status', null);
    }

    public function test_the_login_queues_the_import_rather_than_running_it_inline(): void
    {
        // Inline, the browser would hold the redirect open for the whole import
        // and the frontend could never observe it running.
        Queue::fake();

        $this->login('kc-sub-principal', 'person-teacher');

        Queue::assertPushed(
            ImportSchoolForTenant::class,
            fn (ImportSchoolForTenant $job): bool => $job->tenantId === self::$testTenant->id,
        );

        // Marked before dispatch, so the frontend never sees a school that
        // looks ready only because no worker has picked the job up yet.
        $this->assertSame(SchoolImport::STATUS_PENDING, self::$testTenant->fresh()->idp_import_status);
        $this->assertFalse($this->importStatus()['ready']);
    }

    public function test_first_sso_login_takes_over_the_admin_and_imports_the_school(): void
    {
        $this->login('kc-sub-principal', 'person-teacher');

        self::$testTenant->run(function () {
            $admins = LegacyUser::where('userlevel', '>=', 50)->get();

            // One admin, not two: the seeded row became the principal's account.
            $this->assertCount(1, $admins, 'SSO must not create a second admin');
            $this->assertSame(self::ADMIN_USERNAME, $admins[0]->username);
            $this->assertSame('kc-sub-principal', $admins[0]->sso_sub);
            $this->assertSame('person-teacher', $admins[0]->idp_user_id);
            $this->assertSame(50, $admins[0]->userlevel->value);
        });

        $tenant = self::$testTenant->fresh();
        $this->assertSame(SchoolImport::STATUS_COMPLETED, $tenant->idp_import_status);
        $this->assertSame(2, (int) $tenant->idp_import_rooms);
    }

    public function test_the_import_brings_in_rooms_and_the_rest_of_the_school(): void
    {
        $this->login('kc-sub-principal', 'person-teacher');

        self::$testTenant->run(function () {
            $this->assertSame(2, DB::table('au_rooms')->whereNotNull('idp_group_id')->count());
            // Principal plus the two people who have not logged in yet.
            $this->assertSame(3, LegacyUser::whereNotNull('idp_user_id')->count());
            $this->assertSame(1, LegacyUser::whereNotNull('sso_sub')->count());
        });
    }

    public function test_a_later_user_finds_their_account_already_there(): void
    {
        $this->login('kc-sub-principal', 'person-teacher');

        $before = self::$testTenant->run(fn () => LegacyUser::count());

        $this->login('kc-sub-student', 'person-student');

        self::$testTenant->run(function () use ($before) {
            $this->assertSame($before, LegacyUser::count(), 'the second login must not create an account');

            $student = LegacyUser::where('idp_user_id', 'person-student')->firstOrFail();
            $this->assertSame('kc-sub-student', $student->sso_sub);
            $this->assertSame(20, $student->userlevel->value);
        });
    }

    public function test_the_second_login_does_not_re_run_the_import(): void
    {
        $this->login('kc-sub-principal', 'person-teacher');
        $firstFinishedAt = self::$testTenant->fresh()->idp_import_finished_at;

        $this->login('kc-sub-student', 'person-student');

        $this->assertEquals($firstFinishedAt, self::$testTenant->fresh()->idp_import_finished_at);
    }

    public function test_import_status_is_reported_to_the_frontend(): void
    {
        $this->login('kc-sub-principal', 'person-teacher');

        $jwt = self::$testTenant->run(fn () => app(LegacyJwtService::class)->generateToken(
            LegacyUser::where('username', self::ADMIN_USERNAME)->firstOrFail(),
        ));

        $response = $this->getJson('/api/v2/auth/idp/import-status', [
            'aula-instance-code' => 'TEST001',
            'Authorization' => "Bearer {$jwt}",
        ]);

        $response->assertOk()
            ->assertJsonPath('ready', true)
            ->assertJsonPath('status', SchoolImport::STATUS_COMPLETED)
            ->assertJsonPath('rooms', 2)
            ->assertJsonPath('users', 3);
    }

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * @return array<string, mixed>
     */
    private function importStatus(): array
    {
        $jwt = self::$testTenant->run(fn () => app(LegacyJwtService::class)->generateToken(
            LegacyUser::where('username', self::ADMIN_USERNAME)->firstOrFail(),
        ));

        return $this->getJson('/api/v2/auth/idp/import-status', [
            'aula-instance-code' => 'TEST001',
            'Authorization' => "Bearer {$jwt}",
        ])->json();
    }

    private function login(string $keycloakSub, string $eduplacesPersonId): void
    {
        // One closure covering JWKS, the Keycloak broker and the IDM.
        // Http::fake() merges successive calls rather than replacing them, so
        // layering separate stubs would leave the first login's responses
        // winning for the second.
        $this->currentPersonId = $eduplacesPersonId;
        $this->currentKeycloakSub = $keycloakSub;
        $this->fakeEverything();
        $this->mockSocialite();

        $payload = base64_encode((string) json_encode(['instance_code' => 'TEST001', 'nonce' => 'n']));
        $state = $payload.'.'.hash_hmac('sha256', $payload, (string) config('app.key'));

        $this->get("/api/v2/auth/sso/callback?state={$state}")->assertRedirect();
    }

    /**
     * Registered once per test and driven by $currentKeycloakSub.
     *
     * Mockery expectations accumulate, so re-mocking Socialite for a second
     * login would leave the first expectation matching and hand back the first
     * user — every login after the first would silently be the same person.
     */
    private function mockSocialite(): void
    {
        if ($this->socialiteMocked) {
            return;
        }

        $this->socialiteMocked = true;

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturnUsing(fn () => $this->socialiteUser());

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);
    }

    private function socialiteUser(): User
    {
        $idToken = $this->signIdToken([
            'iss' => self::KEYCLOAK_BASE.'/realms/'.self::KEYCLOAK_REALM,
            'aud' => self::KEYCLOAK_CLIENT_ID,
            'azp' => self::KEYCLOAK_CLIENT_ID,
            'iat' => time() - 30,
            'exp' => time() + 600,
            'sub' => $this->currentKeycloakSub,
            'email_verified' => true,
        ]);

        $user = \Mockery::mock(User::class);
        $user->token = 'kc-access-token';
        $user->refreshToken = 'kc-refresh-token';
        $user->accessTokenResponseBody = ['id_token' => $idToken];
        $user->shouldReceive('getId')->andReturn($this->currentKeycloakSub);
        // Eduplaces users have no email, and the id_token carries none.
        $user->shouldReceive('getEmail')->andReturn(null);
        $user->shouldReceive('getName')->andReturn(null);
        $user->shouldReceive('getNickname')->andReturn(null);

        return $user;
    }

    private function fakeEverything(): void
    {
        $groups = [
            ['id' => 'group-5a', 'name' => 'Klasse 5a'],
            ['id' => 'group-5b', 'name' => 'Klasse 5b'],
        ];

        $people = [
            ['id' => 'person-teacher', 'role' => 'TEACHER', 'name' => ['firstCall' => 'Stephanie', 'last' => 'Schuster'], 'groups' => [$groups[0]]],
            ['id' => 'person-student', 'role' => 'STUDENT', 'name' => ['firstCall' => 'Johanna', 'last' => 'Becker'], 'groups' => [$groups[0]]],
            ['id' => 'person-other', 'role' => 'STUDENT', 'name' => ['firstCall' => 'Ali', 'last' => 'Yilmaz'], 'groups' => [$groups[1]]],
        ];

        Http::fake(function (Request $request) use ($groups, $people) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                str_contains($path, 'openid-connect/certs') => Http::response($this->jwksDocument()),
                str_contains($path, '/broker/eduplaces/token') => Http::response([
                    'id_token' => $this->upstreamIdToken($this->currentPersonId),
                ]),
                str_ends_with($path, '/oauth2/token') => Http::response([
                    'access_token' => 'token', 'token_type' => 'bearer', 'expires_in' => 3599,
                ]),
                (bool) preg_match('#/schools/[^/]+/groups$#', $path) => Http::response($groups),
                (bool) preg_match('#/schools/[^/]+/people$#', $path) => Http::response($people),
                (bool) preg_match('#/schools/[^/]+/users$#', $path) => Http::response([]),
                (bool) preg_match('#/(people|users)/([^/]+)$#', $path, $m) => $this->onePerson($people, urldecode($m[2])),
                default => Http::response(status: 404),
            };
        });
    }

    /**
     * The upstream Eduplaces id_token Keycloak hands back through its broker
     * endpoint. Only the payload matters — Keycloak is the trust boundary, so
     * the controller decodes it without verifying a signature.
     */
    private function upstreamIdToken(string $personId): string
    {
        $header = rtrim(strtr(base64_encode((string) json_encode(['alg' => 'RS256'])), '+/', '-_'), '=');
        $body = rtrim(strtr(base64_encode((string) json_encode([
            'sub' => $personId,
            'school' => self::SCHOOL,
        ])), '+/', '-_'), '=');

        return "{$header}.{$body}.sig";
    }

    /**
     * @param  list<array<string, mixed>>  $people
     */
    private function onePerson(array $people, string $id): PromiseInterface
    {
        foreach ($people as $person) {
            if ($person['id'] === $id) {
                return Http::response($person);
            }
        }

        return Http::response(status: 404);
    }

    private function seedTenantAdmin(): void
    {
        self::$testTenant->run(function () {
            $user = new LegacyUser;
            $user->username = self::ADMIN_USERNAME;
            $user->displayname = 'Tenant Admin';
            $user->email = 'bootstrap_admin@test.example';
            $user->pw = password_hash('initial', PASSWORD_BCRYPT);
            $user->userlevel = 50;
            $user->status = LegacyUser::STATUS_ACTIVE;
            $user->hash_id = md5(self::ADMIN_USERNAME);
            $user->save();
        });
    }

    private function cleanTenant(): void
    {
        self::$testTenant->run(function () {
            $userIds = LegacyUser::whereNotNull('idp_user_id')
                ->orWhere('username', self::ADMIN_USERNAME)
                // Rows provisioned by a login carry neither, so clear those too
                // or a stray sso_sub makes the next test think the tenant has
                // already been bootstrapped.
                ->orWhere('sso_sub', 'like', 'kc-sub-%')
                ->pluck('id')->all();
            $roomIds = DB::table('au_rooms')->whereNotNull('idp_group_id')->pluck('id')->all();

            if ($roomIds !== []) {
                DB::table('au_rel_rooms_users')->whereIn('room_id', $roomIds)->delete();
                DB::table('au_rooms')->whereIn('id', $roomIds)->delete();
            }

            if ($userIds !== []) {
                DB::table('au_rel_rooms_users')->whereIn('user_id', $userIds)->delete();
                LegacyUser::whereIn('id', $userIds)->delete();
            }
        });
    }
}
