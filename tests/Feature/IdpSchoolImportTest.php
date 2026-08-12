<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ImportSchoolForTenant;
use App\Models\IdpDirectoryEntry;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\Idp\SchoolImport;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * The initial school import: Eduplaces groups become aula rooms, Eduplaces
 * people become users enrolled in those rooms with a per-room role.
 */
class SchoolImportTest extends TestCase
{
    use CreatesTestTenant;

    private const string API_URL = 'https://api.eduplaces.test';

    private const string AUTH_URL = 'https://auth.eduplaces.test';

    private const string SCHOOL = 'school-import-test';

    /** @var list<array<string, mixed>> */
    private array $idmGroups = [];

    /** @var list<array<string, mixed>> */
    private array $idmPeople = [];

    private bool $idmBroken = false;

    private bool $peopleForbidden = false;

    private bool $usersListingEmpty = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        // Shared across test classes, so the in-memory copy goes stale and
        // Eloquent would skip writes it wrongly believes are no-ops.
        self::$testTenant->refresh();
        self::$testTenant->update(['idp_school_id' => self::SCHOOL, 'sso_provider' => 'eduplaces']);

        config([
            'idp.providers.eduplaces.auth_url' => self::AUTH_URL,
            'idp.providers.eduplaces.api_url' => self::API_URL,
            'idp.providers.eduplaces.client_id' => 'test-client',
            'idp.providers.eduplaces.client_secret' => 'test-secret',
            'idp.providers.eduplaces.roles' => ['TEACHER' => 40, 'STUDENT' => 20],
            'idp.providers.eduplaces.default_role' => 20,
        ]);

        Cache::flush();
        IdpDirectoryEntry::query()->delete();
        $this->cleanTenant();

        $this->idmGroups = [];
        $this->idmPeople = [];
        $this->idmBroken = false;
        $this->peopleForbidden = false;
        $this->usersListingEmpty = false;
        $this->fakeIdm();
    }

    protected function tearDown(): void
    {
        $this->cleanTenant();
        IdpDirectoryEntry::query()->delete();
        Tenant::where('id', self::$testTenant->id)->update([
            'idp_school_id' => null,
            'idp_import_status' => null,
            'idp_migration_status' => null,
        ]);
        parent::tearDown();
    }

    public function test_imports_groups_as_rooms_and_people_as_users(): void
    {
        $this->seedSchool();

        $this->import();

        self::$testTenant->run(function () {
            $rooms = DB::table('au_rooms')->whereNotNull('idp_group_id')->get();
            $users = LegacyUser::whereNotNull('idp_user_id')->get();

            $this->assertCount(2, $rooms, 'each Eduplaces group should become a room');
            $this->assertSame(['Klasse 5a', 'Klasse 5b'], $rooms->pluck('room_name')->sort()->values()->all());
            $this->assertCount(3, $users);
            // au_groups plays no part in the integration.
            $this->assertSame(0, DB::table('au_groups')->count());
        });
    }

    public function test_enrols_users_into_their_rooms_with_a_role(): void
    {
        $this->seedSchool();

        $this->import();

        self::$testTenant->run(function () {
            $room = DB::table('au_rooms')->where('idp_group_id', 'group-5a')->first();
            $teacher = LegacyUser::where('idp_user_id', 'person-teacher')->firstOrFail();
            $student = LegacyUser::where('idp_user_id', 'person-student')->firstOrFail();

            $this->assertSame(1, DB::table('au_rel_rooms_users')
                ->where('room_id', $room->id)->where('user_id', $teacher->id)->count());
            $this->assertSame(1, DB::table('au_rel_rooms_users')
                ->where('room_id', $room->id)->where('user_id', $student->id)->count());

            // The per-room role in the roles column, not just the membership row.
            $this->assertSame(40, $this->roleFor($teacher, (string) $room->hash_id));
            $this->assertSame(20, $this->roleFor($student, (string) $room->hash_id));
        });
    }

    public function test_takes_real_names_from_group_members(): void
    {
        $this->seedSchool();

        $this->import();

        self::$testTenant->run(function () {
            $teacher = LegacyUser::where('idp_user_id', 'person-teacher')->firstOrFail();

            $this->assertSame('Stephanie Schuster', $teacher->displayname);
            $this->assertSame('Stephanie Schuster', $teacher->realname);
            $this->assertStringStartsWith('stephanie.schuster', (string) $teacher->username);
        });
    }

    public function test_falls_back_to_the_pseudonym_when_no_real_name_is_exposed(): void
    {
        // What an app with pseudonymous entitlements actually sees on /users:
        // a pseudonym and no name at all.
        $this->idmGroups = [];
        $this->idmPeople = [[
            'id' => 'person-pseudo',
            'role' => 'STUDENT',
            'pseudonym' => 'Denk Raumfahrer',
            'groups' => [],
        ]];

        $this->import();

        self::$testTenant->run(function () {
            $user = LegacyUser::where('idp_user_id', 'person-pseudo')->firstOrFail();

            $this->assertSame('Denk Raumfahrer', $user->displayname);
            // A pseudonym is not a legal name and must not land in realname.
            $this->assertNull($user->realname);
            $this->assertStringStartsWith('denk.raumfahrer', (string) $user->username);
        });
    }

    public function test_imports_members_that_only_the_group_listing_reveals(): void
    {
        // A person can appear in a group's members and be absent from /users.
        // Reading only /users would silently lose them.
        $this->idmGroups = [['id' => 'group-5a', 'name' => 'Klasse 5a']];
        $this->idmPeople = [[
            'id' => 'person-group-only',
            'role' => 'STUDENT',
            'name' => ['firstCall' => 'Nur', 'last' => 'Gruppe'],
            'groups' => [['id' => 'group-5a', 'name' => 'Klasse 5a']],
        ]];
        $this->usersListingEmpty = true;

        $this->import();

        self::$testTenant->run(function () {
            $user = LegacyUser::where('idp_user_id', 'person-group-only')->first();

            $this->assertNotNull($user, 'a group-only member must still be imported');
            $this->assertSame('Nur Gruppe', $user->displayname);

            $room = DB::table('au_rooms')->where('idp_group_id', 'group-5a')->first();
            $this->assertSame(1, DB::table('au_rel_rooms_users')
                ->where('room_id', $room->id)->where('user_id', $user->id)->count());
        });
    }

    public function test_maps_roles_onto_userlevel(): void
    {
        $this->seedSchool();

        $this->import();

        self::$testTenant->run(function () {
            $this->assertSame(40, LegacyUser::where('idp_user_id', 'person-teacher')->firstOrFail()->userlevel->value);
            $this->assertSame(20, LegacyUser::where('idp_user_id', 'person-student')->firstOrFail()->userlevel->value);
        });
    }

    public function test_imported_users_have_no_email(): void
    {
        $this->seedSchool();

        $this->import();

        self::$testTenant->run(function () {
            // Eduplaces exposes no address and its users do not share one;
            // idp_user_id is the identifier.
            $this->assertSame(0, LegacyUser::whereNotNull('idp_user_id')->whereNotNull('email')->count());
            $this->assertSame(3, LegacyUser::whereNotNull('idp_user_id')->count());
        });
    }

    public function test_records_progress_on_the_tenant(): void
    {
        $this->seedSchool();

        $this->import();

        $tenant = self::$testTenant->fresh();

        $this->assertSame(SchoolImport::STATUS_COMPLETED, $tenant->idp_import_status);
        $this->assertSame(2, (int) $tenant->idp_import_rooms);
        $this->assertSame(3, (int) $tenant->idp_import_users);
        $this->assertNotNull($tenant->idp_import_finished_at);
        $this->assertNull($tenant->idp_import_error);
    }

    public function test_a_migrating_school_moves_on_when_the_import_lands(): void
    {
        $this->seedSchool();
        Tenant::where('id', self::$testTenant->id)
            ->update(['idp_migration_status' => Tenant::IDP_MIGRATION_IMPORTING]);

        $this->runImportJob();

        // Without this the school sits on `importing` for good: the import is
        // done and every screen still says it is running.
        $this->assertSame(Tenant::IDP_MIGRATION_LINKING, self::$testTenant->fresh()->idp_migration_status);
    }

    public function test_a_greenfield_school_has_no_migration_state_to_advance(): void
    {
        $this->seedSchool();

        $this->runImportJob();

        $tenant = self::$testTenant->fresh();
        $this->assertNull($tenant->idp_migration_status);
        $this->assertSame(SchoolImport::STATUS_COMPLETED, $tenant->idp_import_status);
    }

    public function test_a_migration_whose_import_gave_up_goes_back_to_the_review(): void
    {
        Tenant::where('id', self::$testTenant->id)
            ->update(['idp_migration_status' => Tenant::IDP_MIGRATION_IMPORTING]);

        (new ImportSchoolForTenant(self::$testTenant->id))->failed(new \RuntimeException('worker died'));

        // The review is where an admin can look at the proposal and try again;
        // a progress screen that never finishes is not.
        $tenant = self::$testTenant->fresh();
        $this->assertSame(Tenant::IDP_MIGRATION_REVIEWING, $tenant->idp_migration_status);
        $this->assertSame(SchoolImport::STATUS_FAILED, $tenant->idp_import_status);
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $this->seedSchool();

        $this->import();
        $this->import();

        self::$testTenant->run(function () {
            $this->assertSame(2, DB::table('au_rooms')->whereNotNull('idp_group_id')->count());
            $this->assertSame(3, LegacyUser::whereNotNull('idp_user_id')->count());

            $teacher = LegacyUser::where('idp_user_id', 'person-teacher')->firstOrFail();
            $roles = json_decode((string) $teacher->roles, true);
            $rooms = array_column($roles, 'room');
            $this->assertSame($rooms, array_unique($rooms), 'roles must not accumulate duplicate room entries');
        });
    }

    public function test_moving_a_student_between_classes_unenrols_them(): void
    {
        $this->seedSchool();
        $this->import();

        // Student moves from 5a to 5b.
        $this->idmPeople = array_map(function (array $p): array {
            if ($p['id'] === 'person-student') {
                $p['groups'] = [['id' => 'group-5b', 'name' => 'Klasse 5b']];
            }

            return $p;
        }, $this->idmPeople);

        $this->import();

        self::$testTenant->run(function () {
            $student = LegacyUser::where('idp_user_id', 'person-student')->firstOrFail();
            $old = DB::table('au_rooms')->where('idp_group_id', 'group-5a')->first();
            $new = DB::table('au_rooms')->where('idp_group_id', 'group-5b')->first();

            $this->assertSame(0, DB::table('au_rel_rooms_users')->where('room_id', $old->id)->where('user_id', $student->id)->count());
            $this->assertSame(1, DB::table('au_rel_rooms_users')->where('room_id', $new->id)->where('user_id', $student->id)->count());
            $this->assertSame(20, $this->roleFor($student, (string) $new->hash_id));
            $this->assertNull($this->roleFor($student, (string) $old->hash_id), 'the old class role must be gone');
        });
    }

    public function test_does_not_demote_an_admin(): void
    {
        $this->seedSchool();

        // The person who bootstrapped the tenant is an ordinary teacher to
        // Eduplaces but administers the school in aula.
        $adminId = (int) self::$testTenant->run(function () {
            $u = new LegacyUser;
            $u->username = 'import.admin';
            $u->displayname = 'Import Admin';
            $u->idp_user_id = 'person-teacher';
            $u->userlevel = 50;
            $u->status = LegacyUser::STATUS_ACTIVE;
            $u->hash_id = md5('import.admin');
            $u->save();

            return $u->id;
        });

        $this->import();

        self::$testTenant->run(function () use ($adminId) {
            $this->assertSame(50, LegacyUser::find($adminId)->userlevel->value);
        });
    }

    public function test_imports_from_users_alone_when_people_is_not_granted(): void
    {
        // `people:read` is a separate Eduplaces scope an app may not hold. Over
        // `/users` it adds only sourceSystemIdentifier, which nothing reads, so
        // a school must still import completely without it.
        $this->seedSchool();
        $this->peopleForbidden = true;

        $this->import();

        $this->assertSame(SchoolImport::STATUS_COMPLETED, self::$testTenant->fresh()->idp_import_status);

        self::$testTenant->run(function () {
            $this->assertSame(2, DB::table('au_rooms')->whereNotNull('idp_group_id')->count());
            $this->assertSame(3, LegacyUser::whereNotNull('idp_user_id')->count());
        });
    }

    public function test_marks_the_tenant_failed_when_the_idm_breaks(): void
    {
        // Flag rather than a second Http::fake(): fakes merge, so a new stub
        // for an already-stubbed URL never gets a look in.
        $this->idmBroken = true;

        try {
            $this->import();
            $this->fail('the import should have surfaced the failure');
        } catch (\Throwable) {
            // expected
        }

        $tenant = self::$testTenant->fresh();

        $this->assertSame(SchoolImport::STATUS_FAILED, $tenant->idp_import_status);
        $this->assertNotNull($tenant->idp_import_error);
    }

    public function test_indexes_everything_it_imported(): void
    {
        $this->seedSchool();

        $this->import();

        // The directory is warm, so the first webhook for any of these skips
        // the discovery scan entirely.
        $this->assertSame(3, IdpDirectoryEntry::where('entity_type', IdpDirectoryEntry::TYPE_USER)->count());
        $this->assertSame(2, IdpDirectoryEntry::where('entity_type', IdpDirectoryEntry::TYPE_GROUP)->count());
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function import(): void
    {
        $tenant = self::$testTenant->fresh();

        $tenant->run(fn () => $this->app->make(SchoolImport::class)->run($tenant));
    }

    /**
     * Through the job rather than the service: advancing the migration is the
     * job's responsibility, so calling the service alone would not see it.
     */
    private function runImportJob(): void
    {
        (new ImportSchoolForTenant(self::$testTenant->id))->handle($this->app->make(SchoolImport::class));
    }

    private function seedSchool(): void
    {
        $this->idmGroups = [
            ['id' => 'group-5a', 'name' => 'Klasse 5a'],
            ['id' => 'group-5b', 'name' => 'Klasse 5b'],
        ];

        $this->idmPeople = [
            [
                'id' => 'person-teacher',
                'role' => 'TEACHER',
                'name' => ['firstFull' => 'Stephanie', 'firstCall' => 'Stephanie', 'last' => 'Schuster'],
                'groups' => [['id' => 'group-5a', 'name' => 'Klasse 5a']],
            ],
            [
                'id' => 'person-student',
                'role' => 'STUDENT',
                'name' => ['firstFull' => 'Wilma Johanna Sophie', 'firstCall' => 'Johanna', 'last' => 'Becker'],
                'groups' => [['id' => 'group-5a', 'name' => 'Klasse 5a']],
            ],
            [
                'id' => 'person-noclass',
                'role' => 'STUDENT',
                'name' => ['firstCall' => 'Ohne', 'last' => 'Klasse'],
                'groups' => [],
            ],
        ];
    }

    private function fakeIdm(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if ($this->idmBroken && ! str_ends_with($path, '/oauth2/token')) {
                return Http::response(status: 503);
            }

            return match (true) {
                str_ends_with($path, '/oauth2/token') => Http::response([
                    'access_token' => 'token', 'token_type' => 'bearer', 'expires_in' => 3599,
                ]),
                (bool) preg_match('#/schools/[^/]+/groups$#', $path) => Http::response(array_map(
                    fn (array $g): array => ['id' => $g['id'], 'name' => $g['name']],
                    $this->idmGroups,
                )),
                (bool) preg_match('#/groups/([^/]+)$#', $path, $g) => $this->groupResponse(urldecode($g[1])),
                (bool) preg_match('#/schools/[^/]+/people$#', $path) => $this->peopleForbidden
                    ? Http::response(status: 403)
                    : Http::response($this->idmPeople),
                (bool) preg_match('#/schools/[^/]+/users$#', $path) => Http::response(
                    $this->usersListingEmpty ? [] : ($this->peopleForbidden ? $this->idmPeople : []),
                ),
                (bool) preg_match('#/(people|users)/([^/]+)$#', $path, $m) => $this->personResponse(urldecode($m[2])),
                default => Http::response(status: 404),
            };
        });
    }

    /**
     * IdpGroup detail carries the member list with real names — the only place
     * Eduplaces exposes them when the app holds pseudonymous entitlements.
     */
    private function groupResponse(string $id): PromiseInterface
    {
        foreach ($this->idmGroups as $group) {
            if ($group['id'] === $id) {
                $members = [];

                foreach ($this->idmPeople as $person) {
                    foreach ($person['groups'] as $ref) {
                        if ($ref['id'] === $id) {
                            $members[] = [
                                'id' => $person['id'],
                                'role' => $person['role'],
                                'name' => $person['name'],
                            ];
                        }
                    }
                }

                return Http::response($group + ['members' => $members]);
            }
        }

        return Http::response(status: 404);
    }

    private function personResponse(string $id): PromiseInterface
    {
        foreach ($this->idmPeople as $person) {
            if ($person['id'] === $id) {
                return Http::response($person);
            }
        }

        return Http::response(status: 404);
    }

    /**
     * The role this user holds in one room, or null if they hold none.
     */
    private function roleFor(LegacyUser $user, string $roomHashId): ?int
    {
        foreach (json_decode((string) $user->roles, true) ?: [] as $entry) {
            if (($entry['room'] ?? null) === $roomHashId) {
                return (int) $entry['role'];
            }
        }

        return null;
    }

    private function cleanTenant(): void
    {
        self::$testTenant->run(function () {
            $userIds = LegacyUser::whereNotNull('idp_user_id')
                ->orWhere('username', 'like', 'import.%')
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
