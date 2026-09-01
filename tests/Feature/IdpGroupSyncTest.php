<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Jobs\ProcessIdpWebhookEvent;
use App\Models\IdpDirectoryEntry;
use App\Models\IdpWebhookEvent;
use App\Models\LegacyUser;
use App\Services\Idp\Dto\IdpEvent;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * Covers GroupSync, which handles `group` events after SchoolImport has run. A
 * directory group is an aula room, so these keep `au_rooms` and
 * `au_rel_rooms_users` in step.
 */
class GroupSyncTest extends TestCase
{
    use CreatesTestTenant;

    private const string API_URL = 'https://api.eduplaces.test';

    private const string AUTH_URL = 'https://auth.eduplaces.test';

    private const string SCHOOL = 'school-room-sync';

    private const string GROUP = 'group-room-sync';

    /** @var array<string, array<string, mixed>> */
    private array $idmGroups = [];

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
        IdpWebhookEvent::query()->delete();
        IdpDirectoryEntry::query()->delete();
        $this->cleanTenant();

        $this->idmGroups = [];
        $this->fakeIdm();
    }

    protected function tearDown(): void
    {
        $this->cleanTenant();
        IdpWebhookEvent::query()->delete();
        IdpDirectoryEntry::query()->delete();
        self::$testTenant->update(['idp_school_id' => null]);
        parent::tearDown();
    }

    public function test_creates_a_room_for_a_new_group(): void
    {
        $this->setGroup(['name' => 'Klasse 5a']);

        $this->process($this->event('create'));

        $room = $this->room();

        $this->assertNotNull($room);
        $this->assertSame('Klasse 5a', $room->room_name);
        $this->assertSame(1, (int) $room->status);
        // `au_groups` plays no part in the integration.
        $this->assertSame(0, self::$testTenant->run(fn () => DB::table('au_groups')->count()));
    }

    public function test_renames_a_room_in_place(): void
    {
        $this->setGroup(['name' => 'Klasse 5a']);
        $this->process($this->event('create'));
        $originalId = $this->room()->id;

        $this->setGroup(['name' => 'Klasse 6a']);
        $this->process($this->event('update', ['name']));

        $this->assertSame($originalId, $this->room()->id);
        $this->assertSame('Klasse 6a', $this->room()->room_name);
    }

    public function test_enrols_members_with_their_role(): void
    {
        $teacherId = $this->seedUser('person-teacher');
        $studentId = $this->seedUser('person-student');

        $this->setGroup(['name' => 'Klasse 5a', 'members' => [
            ['id' => 'person-teacher', 'role' => 'TEACHER', 'name' => ['last' => 'Schuster']],
            ['id' => 'person-student', 'role' => 'STUDENT', 'name' => ['last' => 'Becker']],
        ]]);

        $this->process($this->event('create'));

        $room = $this->room();

        $this->assertMembership((int) $room->id, $teacherId, 1);
        $this->assertMembership((int) $room->id, $studentId, 1);
        $this->assertSame(40, $this->roleFor($teacherId, (string) $room->hash_id));
        $this->assertSame(20, $this->roleFor($studentId, (string) $room->hash_id));
    }

    public function test_unenrols_a_member_the_idm_dropped(): void
    {
        $stays = $this->seedUser('person-stays');
        $goes = $this->seedUser('person-goes');

        $this->setGroup(['name' => 'Klasse 5a', 'members' => [
            ['id' => 'person-stays', 'role' => 'STUDENT', 'name' => ['last' => 'Stays']],
            ['id' => 'person-goes', 'role' => 'STUDENT', 'name' => ['last' => 'Goes']],
        ]]);
        $this->process($this->event('create'));

        $this->setGroup(['name' => 'Klasse 5a', 'members' => [
            ['id' => 'person-stays', 'role' => 'STUDENT', 'name' => ['last' => 'Stays']],
        ]]);
        $this->process($this->event('update', ['name']));

        $room = $this->room();

        $this->assertMembership((int) $room->id, $stays, 1);
        $this->assertMembership((int) $room->id, $goes, 0);
        // The `roles` entry is removed with the membership row.
        $this->assertNull($this->roleFor($goes, (string) $room->hash_id));
    }

    public function test_leaves_aula_native_members_alone(): void
    {
        $this->setGroup(['name' => 'Klasse 5a', 'members' => []]);
        $this->process($this->event('create'));

        $room = $this->room();
        $native = $this->seedUser(null);

        self::$testTenant->run(fn () => DB::table('au_rel_rooms_users')->insert([
            'room_id' => $room->id, 'user_id' => $native, 'status' => 1, 'updater_id' => 0,
        ]));

        $this->process($this->event('update', ['name']));

        // A member with no idp_user_id was added inside aula and stays.
        $this->assertMembership((int) $room->id, $native, 1);
    }

    public function test_skips_members_aula_does_not_hold_yet(): void
    {
        $this->setGroup(['name' => 'Klasse 5a', 'members' => [
            ['id' => 'person-unknown', 'role' => 'STUDENT', 'name' => ['last' => 'Unknown']],
        ]]);

        $this->process($this->event('create'));

        $this->assertSame(0, self::$testTenant->run(
            fn () => LegacyUser::where('idp_user_id', 'person-unknown')->count(),
        ));
    }

    public function test_delete_archives_the_room(): void
    {
        $this->setGroup(['name' => 'Klasse 5a']);
        $this->process($this->event('create'));

        $this->process($this->event('delete'));

        $this->assertSame(3, (int) $this->room()->status);
    }

    public function test_restore_reactivates_the_room(): void
    {
        $this->setGroup(['name' => 'Klasse 5a']);
        $this->process($this->event('create'));
        $this->process($this->event('delete'));

        $this->process($this->event('restore'));

        $this->assertSame(1, (int) $this->room()->status);
    }

    public function test_applying_the_same_event_twice_does_not_duplicate(): void
    {
        $this->setGroup(['name' => 'Klasse 5a']);

        $this->process($this->event('create'));
        $this->process($this->event('create'));

        $this->assertSame(1, self::$testTenant->run(
            fn () => DB::table('au_rooms')->where('idp_group_id', self::GROUP)->count(),
        ));
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function process(IdpWebhookEvent $event): void
    {
        $this->app->call([new ProcessIdpWebhookEvent($event->id), 'handle']);
    }

    private function event(string $action, array $properties = []): IdpWebhookEvent
    {
        return IdpWebhookEvent::create([
            'provider' => 'eduplaces',
            'entity_type' => IdpEvent::ENTITY_GROUP,
            'action' => $action,
            'entity_id' => self::GROUP,
            'updated_properties' => $properties,
            'payload' => ['event' => 'group', 'action' => $action, 'groupId' => self::GROUP],
            'status' => IdpWebhookEvent::STATUS_PENDING,
            'received_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function setGroup(array $group): void
    {
        $this->idmGroups[self::GROUP] = array_merge(['id' => self::GROUP, 'members' => []], $group);
    }

    private function fakeIdm(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                str_ends_with($path, '/oauth2/token') => Http::response([
                    'access_token' => 'token', 'token_type' => 'bearer', 'expires_in' => 3599,
                ]),
                (bool) preg_match('#/schools/[^/]+/(people|users)$#', $path) => Http::response([]),
                (bool) preg_match('#/schools/[^/]+/groups$#', $path) => Http::response(
                    array_values(array_map(
                        fn (array $g): array => ['id' => $g['id'], 'name' => $g['name'] ?? ''],
                        $this->idmGroups,
                    )),
                ),
                (bool) preg_match('#/groups/([^/]+)$#', $path, $m) => $this->groupResponse(urldecode($m[1])),
                default => Http::response(status: 404),
            };
        });
    }

    private function groupResponse(string $groupId): PromiseInterface
    {
        return isset($this->idmGroups[$groupId])
            ? Http::response($this->idmGroups[$groupId])
            : Http::response(status: 404);
    }

    private function room(): ?object
    {
        return self::$testTenant->run(
            fn () => DB::table('au_rooms')->where('idp_group_id', self::GROUP)->first(),
        );
    }

    private function seedUser(?string $personId): int
    {
        return (int) self::$testTenant->run(function () use ($personId) {
            $user = new LegacyUser;
            $user->username = 'roomtest.'.($personId ?? 'native').'.'.random_int(1000, 999999);
            $user->displayname = $user->username;
            $user->idp_user_id = $personId;
            $user->status = UserStatus::Active;
            $user->userlevel = 20;
            $user->hash_id = md5($user->username);
            $user->save();

            return $user->id;
        });
    }

    private function assertMembership(int $roomId, int $userId, int $expected): void
    {
        $this->assertSame($expected, self::$testTenant->run(
            fn () => DB::table('au_rel_rooms_users')->where('room_id', $roomId)->where('user_id', $userId)->count(),
        ));
    }

    private function roleFor(int $userId, string $roomHashId): ?int
    {
        $roles = self::$testTenant->run(
            fn () => DB::table('au_users_basedata')->where('id', $userId)->value('roles'),
        );

        foreach (json_decode((string) $roles, true) ?: [] as $entry) {
            if (($entry['room'] ?? null) === $roomHashId) {
                return (int) $entry['role'];
            }
        }

        return null;
    }

    private function cleanTenant(): void
    {
        self::$testTenant->run(function () {
            $roomIds = DB::table('au_rooms')->whereNotNull('idp_group_id')->pluck('id')->all();
            $userIds = LegacyUser::where('username', 'like', 'roomtest.%')->pluck('id')->all();

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
