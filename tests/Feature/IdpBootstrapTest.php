<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
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
use SocialiteProviders\Manager\OAuth2\User;
use Tests\Concerns\CreatesTestTenant;
use Tests\Support\SignsIdTokens;
use Tests\TestCase;

/**
 * Covers SsoController::bootstrapIdpTenant() end to end:
 *
 *   1. a tenant is created with one seeded admin
 *   2. the first SSO login arrives
 *   3. that login claims the admin row and dispatches ImportSchoolForTenant
 *   4. a later login finds an account the import already created
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

    /** School the next login's upstream id_token claims to come from. */
    private string $currentSchool = self::SCHOOL;

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
            // Left null: learnSchoolId() sets it from the upstream `school`
            // claim on the first login.
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
            'idp_migration_status' => null,
        ]);
        parent::tearDown();
    }

    public function test_learns_the_school_id_from_the_first_login(): void
    {
        $this->assertNull(self::$testTenant->fresh()->idp_school_id, 'precondition: nothing configured by hand');

        $this->login('kc-sub-principal', 'person-teacher');

        // learnSchoolId() took idp_school_id from the id_token, with no
        // operator configuration.
        $this->assertSame(self::SCHOOL, self::$testTenant->fresh()->idp_school_id);
    }

    // =========================================================
    // school binding
    // =========================================================

    /**
     * Only the first login sets tenants.idp_school_id. rejectForeignSchool()
     * refuses every later login whose `school` claim differs from it.
     */
    public function test_refuses_a_login_from_a_different_school(): void
    {
        $this->login('kc-sub-principal', 'person-teacher');
        $this->assertSame(self::SCHOOL, self::$testTenant->fresh()->idp_school_id);

        $this->socialiteMocked = false;
        $this->login('kc-sub-outsider', 'person-outsider', 'school-somewhere-else');

        self::$testTenant->run(function () {
            $this->assertNull(
                LegacyUser::where('sso_sub', 'kc-sub-outsider')->first(),
                'a login from another school must not be provisioned an account',
            );
        });
    }

    /**
     * A login to the wrong instance_code: another tenant already holds the
     * school, so learnSchoolId() returns idp_school_taken and the bootstrap
     * declines. With idp_school_id still null, rejectForeignSchool() has
     * nothing to check the login against and refuses it.
     */
    public function test_refuses_a_login_when_the_tenant_has_no_school_of_its_own(): void
    {
        $rival = Tenant::create([
            'name' => 'Rival Eduplaces School',
            'instance_code' => 'RIVAL3',
            'api_base_url' => 'https://rival3.example',
            'admin1_username' => 'rival3_admin',
            'admin1_email' => 'rival3@example.test',
            'idp_school_id' => self::SCHOOL,
        ]);

        try {
            $this->login('kc-sub-nobody', 'person-teacher');

            $this->assertNull(self::$testTenant->fresh()->idp_school_id, 'precondition: the school was not learned');

            self::$testTenant->run(function () {
                $this->assertNull(LegacyUser::where('sso_sub', 'kc-sub-nobody')->first());
            });
        } finally {
            $rival->delete();
        }
    }

    /**
     * An id_token with no `school` claim is refused: that claim is what
     * rejectForeignSchool() checks tenants.idp_school_id against.
     */
    public function test_refuses_a_login_that_carries_no_school_claim(): void
    {
        $this->login('kc-sub-principal', 'person-teacher');
        $this->assertSame(self::SCHOOL, self::$testTenant->fresh()->idp_school_id);

        $this->socialiteMocked = false;
        $this->login('kc-sub-claimless', 'person-student', null);

        self::$testTenant->run(function () {
            $this->assertNull(LegacyUser::where('sso_sub', 'kc-sub-claimless')->first());
        });
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
        // A directory-synced tenant with no import is not ready, even with
        // idp_school_id still unknown. setUp() seeded the admin already, and
        // hash_id is unique, so seeding it again here would collide.
        $jwt = self::$testTenant->run(fn () => app(LegacyJwtService::class)->generateToken(
            LegacyUser::where('username', self::ADMIN_USERNAME)->firstOrFail(),
        ));

        $this->getJson('/api/v2/auth/idp/import-status', [
            'aula-instance-code' => 'TEST001',
            'Authorization' => "Bearer {$jwt}",
        ])->assertOk()->assertJsonPath('ready', false)->assertJsonPath('status', null);
    }

    /**
     * A declined bootstrap writes no idp_import_status and no later login
     * retries it, so waiting on one would hold every user on the setup screen.
     */
    public function test_the_import_status_endpoint_stops_blocking_once_a_bootstrap_has_declined(): void
    {
        self::$testTenant->run(function () {
            $admin = LegacyUser::where('username', self::ADMIN_USERNAME)->firstOrFail();
            $admin->sso_sub = 'kc-sub-already-signed-in';
            $admin->save();
        });

        $jwt = self::$testTenant->run(fn () => app(LegacyJwtService::class)->generateToken(
            LegacyUser::where('username', self::ADMIN_USERNAME)->firstOrFail(),
        ));

        $this->getJson('/api/v2/auth/idp/import-status', [
            'aula-instance-code' => 'TEST001',
            'Authorization' => "Bearer {$jwt}",
        ])->assertOk()->assertJsonPath('ready', true)->assertJsonPath('status', null);
    }

    public function test_it_refuses_to_bootstrap_a_school_that_already_has_users(): void
    {
        // The seeded admin of a school in use is somebody's account, so the
        // first SSO login must not claim it even with idp_migration_status
        // left null.
        self::$testTenant->run(function () {
            $pupil = new LegacyUser;
            $pupil->username = 'existing.pupil';
            $pupil->displayname = 'Existing Pupil';
            $pupil->status = UserStatus::Active;
            $pupil->userlevel = 20;
            $pupil->hash_id = md5('existing.pupil');
            $pupil->save();
        });

        $this->login('kc-sub-outsider', 'person-teacher');

        self::$testTenant->run(function () {
            $admin = LegacyUser::where('username', self::ADMIN_USERNAME)->firstOrFail();

            $this->assertNull($admin->sso_sub, 'the admin account must not have been claimed');
            $this->assertNull($admin->idp_user_id);
        });

        // And no import was dispatched.
        $this->assertNull(self::$testTenant->fresh()->idp_import_status);
    }

    public function test_it_does_not_bootstrap_a_school_that_is_being_migrated(): void
    {
        // A flagged tenant is connected through connectIdentity(), so an early
        // login must not claim the admin row or import the roster.
        Tenant::where('id', self::$testTenant->id)
            ->update(['idp_migration_status' => Tenant::IDP_MIGRATION_FLAGGED]);

        $this->login('kc-sub-early', 'person-teacher');

        self::$testTenant->run(function () {
            $this->assertNull(
                LegacyUser::where('username', self::ADMIN_USERNAME)->firstOrFail()->sso_sub,
            );
        });
        $this->assertNull(self::$testTenant->fresh()->idp_school_id, 'the school must not be learned yet');
        $this->assertNull(self::$testTenant->fresh()->idp_import_status);
    }

    public function test_the_login_queues_the_import_rather_than_running_it_inline(): void
    {
        // Run inline, the import would hold the callback redirect open for its
        // whole duration.
        Queue::fake();

        $this->login('kc-sub-principal', 'person-teacher');

        Queue::assertPushed(
            ImportSchoolForTenant::class,
            fn (ImportSchoolForTenant $job): bool => $job->tenantId === self::$testTenant->id,
        );

        // Written before the dispatch, so ImportStatusController never reports
        // ready while the job is still waiting in the queue.
        $this->assertSame(SchoolImport::STATUS_PENDING, self::$testTenant->fresh()->idp_import_status);
        $this->assertFalse($this->importStatus()['ready']);
    }

    public function test_first_sso_login_takes_over_the_admin_and_imports_the_school(): void
    {
        $this->login('kc-sub-principal', 'person-teacher');

        self::$testTenant->run(function () {
            $admins = LegacyUser::where('userlevel', '>=', 50)->get();

            // One admin, not two: the seeded row became the first login's
            // account.
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
            // The first login plus the two directory users that have not
            // signed in.
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

    /**
     * @param  string|null  $school  school the upstream id_token claims, or null
     *                               to omit the claim entirely
     */
    private function login(string $keycloakSub, string $eduplacesPersonId, ?string $school = self::SCHOOL): void
    {
        $this->currentSchool = (string) $school;

        // One closure covering JWKS, the Keycloak broker and the IDM.
        // Http::fake() merges successive calls rather than replacing them, so
        // separate stubs would leave the first login's responses matching the
        // second.
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
     * login would leave the first expectation matching and return the first
     * user for every login after it.
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
        // The directory exposes no email, and the id_token carries none.
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
     * endpoint. Only the payload matters: Keycloak is the trust boundary, so
     * decodeIdTokenPayload() does not verify the signature.
     */
    private function upstreamIdToken(string $personId): string
    {
        $header = rtrim(strtr(base64_encode((string) json_encode(['alg' => 'RS256'])), '+/', '-_'), '=');
        $body = rtrim(strtr(base64_encode((string) json_encode(array_filter([
            'sub' => $personId,
            'school' => $this->currentSchool === '' ? null : $this->currentSchool,
        ], fn ($value): bool => $value !== null))), '+/', '-_'), '=');

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
            $user->status = UserStatus::Active;
            $user->hash_id = md5(self::ADMIN_USERNAME);
            $user->save();
        });
    }

    /**
     * Reduce the shared tenant to what tenant creation leaves: the seeded admin
     * and nobody else.
     *
     * bootstrapIdpTenant() refuses to run on a tenant that already has users,
     * so a row left behind by another test class would change the scenario
     * under test.
     */
    private function cleanTenant(): void
    {
        self::$testTenant->run(function () {
            $strays = LegacyUser::where('username', '!=', self::ADMIN_USERNAME)->pluck('id')->all();

            if ($strays !== []) {
                DB::table('au_rel_rooms_users')->whereIn('user_id', $strays)->delete();
                DB::table('au_rel_groups_users')->whereIn('user_id', $strays)->delete();
                LegacyUser::whereIn('id', $strays)->delete();
            }
        });

        self::$testTenant->run(function () {
            $userIds = LegacyUser::whereNotNull('idp_user_id')
                ->orWhere('username', self::ADMIN_USERNAME)
                // A row provisioned by a login carries neither, and a stray
                // sso_sub makes bootstrapIdpTenant() decline in the next test.
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
