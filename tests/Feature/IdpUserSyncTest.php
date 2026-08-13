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
 * End-to-end for `person` events: the queued job resolves the tenant, reads the
 * person back from the IDM and converges the local row onto it.
 */
class UserSyncTest extends TestCase
{
    use CreatesTestTenant;

    private const string API_URL = 'https://api.eduplaces.test';

    private const string AUTH_URL = 'https://auth.eduplaces.test';

    private const string SCHOOL = 'school-person-sync';

    private const string PERSON = 'person-sync-1';

    /** @var array<string, array<string, mixed>> the people the IDM stand-in knows about */
    private array $idmPeople = [];

    /** @var list<string> listed at the school, but 404 on individual lookup */
    private array $idmVanished = [];

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
        $this->clearSyncedUsers();

        $this->idmPeople = [];
        $this->idmVanished = [];
        $this->fakeIdm();
    }

    protected function tearDown(): void
    {
        $this->clearSyncedUsers();
        IdpWebhookEvent::query()->delete();
        IdpDirectoryEntry::query()->delete();
        self::$testTenant->update(['idp_school_id' => null]);
        parent::tearDown();
    }

    // =========================================================
    // Provisioning
    // =========================================================

    public function test_creates_a_user_for_a_person_it_has_not_seen(): void
    {
        $this->setPerson(['role' => 'STUDENT', 'name' => ['firstFull' => 'Maximilian', 'firstCall' => 'Max', 'last' => 'Mustermann']]);

        $this->process($this->event('create'));

        $user = $this->syncedUser();

        $this->assertNotNull($user);
        $this->assertSame('Max Mustermann', $user->displayname);
        $this->assertSame('Maximilian Mustermann', $user->realname);
        $this->assertSame(20, $user->userlevel->value);
        $this->assertSame(UserStatus::Active, $user->status);
        // The IDM exposes no address, so the row waits for the first login.
        $this->assertNull($user->email);
    }

    public function test_maps_a_teacher_to_a_higher_userlevel(): void
    {
        $this->setPerson(['role' => 'TEACHER', 'name' => ['firstCall' => 'Stephanie', 'last' => 'Schuster']]);

        $this->process($this->event('create'));

        $this->assertSame(40, $this->syncedUser()->userlevel->value);
    }

    public function test_falls_back_to_the_default_userlevel_for_an_unknown_role(): void
    {
        $this->setPerson(['role' => 'CARETAKER', 'name' => ['last' => 'Schmidt']]);

        $this->process($this->event('create'));

        $this->assertSame(20, $this->syncedUser()->userlevel->value);
    }

    public function test_enrols_a_provisioned_user_in_the_standard_room(): void
    {
        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Roomtest']]);

        $this->process($this->event('create'));

        self::$testTenant->run(function () {
            $user = LegacyUser::where('idp_user_id', self::PERSON)->firstOrFail();
            $room = DB::table('au_rooms')->where('type', 1)->first();

            $this->assertNotNull($room, 'the tenant should have a standard room');
            $this->assertSame(1, DB::table('au_rel_rooms_users')
                ->where('user_id', $user->id)->where('room_id', $room->id)->count());
        });
    }

    public function test_gives_provisioned_users_distinct_usernames(): void
    {
        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Twin']], self::PERSON);
        $this->process($this->event('create'));

        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Twin']], 'person-sync-2');
        $this->process($this->event('create', 'person-sync-2'));

        self::$testTenant->run(function () {
            $usernames = LegacyUser::whereIn('idp_user_id', [self::PERSON, 'person-sync-2'])
                ->pluck('username')->all();

            $this->assertCount(2, $usernames);
            $this->assertCount(2, array_unique($usernames), 'identically named people must not collide');
        });
    }

    // =========================================================
    // Updates
    // =========================================================

    public function test_updates_an_existing_user_in_place(): void
    {
        $this->setPerson(['role' => 'STUDENT', 'name' => ['firstCall' => 'Max', 'last' => 'Mustermann']]);
        $this->process($this->event('create'));
        $originalId = $this->syncedUser()->id;

        $this->setPerson(['role' => 'TEACHER', 'name' => ['firstCall' => 'Maxine', 'last' => 'Mustermann']]);
        $this->process($this->event('update', properties: ['name', 'role']));

        $user = $this->syncedUser();

        $this->assertSame($originalId, $user->id, 'the same row should have been updated');
        $this->assertSame('Maxine Mustermann', $user->displayname);
        $this->assertSame(40, $user->userlevel->value);
    }

    public function test_archives_a_user_the_idm_reports_as_inactive(): void
    {
        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Leaver']]);
        $this->process($this->event('create'));

        $this->setPerson(['role' => 'STUDENT', 'status' => 'INACTIVE', 'name' => ['last' => 'Leaver']]);
        $this->process($this->event('update', properties: ['status']));

        $this->assertSame(UserStatus::Archived, $this->syncedUser()->status);
    }

    public function test_applies_the_same_event_twice_without_changing_anything(): void
    {
        $this->setPerson(['role' => 'STUDENT', 'name' => ['firstCall' => 'Max', 'last' => 'Mustermann']]);

        $this->process($this->event('update'));
        $first = $this->syncedUser();

        $this->process($this->event('update'));
        $second = $this->syncedUser();

        // Eduplaces documents no idempotency key, so redelivery has to be inert.
        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->displayname, $second->displayname);
        $this->assertSame($first->userlevel->value, $second->userlevel->value);
        $this->assertSame(1, self::$testTenant->run(
            fn () => LegacyUser::where('idp_user_id', self::PERSON)->count(),
        ));
    }

    // =========================================================
    // Delete and restore
    // =========================================================

    public function test_delete_archives_rather_than_removes(): void
    {
        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Departed']]);
        $this->process($this->event('create'));
        $userId = $this->syncedUser()->id;

        $this->process($this->event('delete'));

        $user = $this->syncedUser();

        // Legacy tables reference user rows; the history has to survive.
        $this->assertNotNull($user, 'the row must still exist');
        $this->assertSame($userId, $user->id);
        $this->assertSame(UserStatus::Archived, $user->status);
    }

    public function test_delete_does_not_call_the_idm(): void
    {
        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Departed']]);
        $this->process($this->event('create'));

        Http::fake();
        $this->process($this->event('delete'));

        // Nothing to read back: the payload already says what happened.
        Http::assertNothingSent();
    }

    public function test_restore_reactivates_an_archived_user(): void
    {
        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Returner']]);
        $this->process($this->event('create'));
        $this->process($this->event('delete'));

        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Returner']]);
        $this->process($this->event('restore'));

        $this->assertSame(UserStatus::Active, $this->syncedUser()->status);
    }

    public function test_deleting_a_person_we_never_had_is_a_skip_not_a_failure(): void
    {
        // The school is ours, so the event resolves to a tenant, but this
        // person never reached aula — nothing to archive.
        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Neverwas']]);

        $event = $this->event('delete');
        $this->process($event);

        $this->assertSame(IdpWebhookEvent::STATUS_SKIPPED, $event->fresh()->status);
        $this->assertSame('user_not_local', $event->fresh()->error);
    }

    // =========================================================
    // IdpGroup membership
    // =========================================================

    public function test_syncs_group_memberships_for_groups_that_exist_locally(): void
    {
        $roomId = $this->seedLocalRoom('group-known', 'Klasse 5a');

        $this->setPerson([
            'role' => 'STUDENT',
            'name' => ['last' => 'Grouped'],
            'groups' => [['id' => 'group-known', 'name' => 'Klasse 5a']],
        ]);

        $this->process($this->event('update', properties: ['groups']));

        self::$testTenant->run(function () use ($roomId) {
            $user = LegacyUser::where('idp_user_id', self::PERSON)->firstOrFail();

            $this->assertSame(1, DB::table('au_rel_rooms_users')
                ->where('user_id', $user->id)->where('room_id', $roomId)->count());
        });
    }

    public function test_removes_a_membership_the_idm_no_longer_reports(): void
    {
        $roomId = $this->seedLocalRoom('group-known', 'Klasse 5a');

        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Mover'], 'groups' => [['id' => 'group-known', 'name' => 'Klasse 5a']]]);
        $this->process($this->event('update', properties: ['groups']));

        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Mover'], 'groups' => []]);
        $this->process($this->event('update', properties: ['groups']));

        self::$testTenant->run(function () use ($roomId) {
            $user = LegacyUser::where('idp_user_id', self::PERSON)->firstOrFail();

            $this->assertSame(0, DB::table('au_rel_rooms_users')
                ->where('user_id', $user->id)->where('room_id', $roomId)->count());
        });
    }

    public function test_leaves_aula_native_group_memberships_alone(): void
    {
        // A group created inside aula, with no Eduplaces id, is not the IDM's
        // to manage and must survive a membership sync.
        $nativeRoomId = $this->seedLocalRoom(null, 'Schülerzeitung');

        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Native'], 'groups' => []]);
        $this->process($this->event('create'));

        self::$testTenant->run(function () use ($nativeRoomId) {
            $user = LegacyUser::where('idp_user_id', self::PERSON)->firstOrFail();

            DB::table('au_rel_rooms_users')->insert([
                'room_id' => $nativeRoomId, 'user_id' => $user->id, 'status' => 1, 'updater_id' => 0,
            ]);
        });

        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Native'], 'groups' => []]);
        $this->process($this->event('update', properties: ['groups']));

        self::$testTenant->run(function () use ($nativeRoomId) {
            $user = LegacyUser::where('idp_user_id', self::PERSON)->firstOrFail();

            $this->assertSame(1, DB::table('au_rel_rooms_users')
                ->where('user_id', $user->id)->where('room_id', $nativeRoomId)->count());
        });
    }

    // =========================================================
    // Event bookkeeping
    // =========================================================

    public function test_marks_the_event_processed_and_stamps_the_tenant(): void
    {
        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Bookkeeping']]);
        $event = $this->event('create');

        $this->process($event);

        $event = $event->fresh();

        $this->assertSame(IdpWebhookEvent::STATUS_PROCESSED, $event->status);
        $this->assertSame(self::$testTenant->id, $event->tenant_id);
        $this->assertSame(1, $event->attempts);
        $this->assertNotNull($event->processed_at);
    }

    public function test_skips_an_event_for_a_school_we_do_not_host(): void
    {
        // The IDM knows nobody by this id at any school we serve, so the scan
        // comes up empty and there is no tenant to open.
        $event = $this->event('update', 'person-from-another-school');
        $this->process($event);

        $this->assertSame(IdpWebhookEvent::STATUS_SKIPPED, $event->fresh()->status);
        $this->assertSame('tenant_unresolved', $event->fresh()->error);
    }

    public function test_skips_when_the_person_vanished_upstream(): void
    {
        // Listed at the school, so the tenant resolves, but the read-back 404s:
        // a create followed closely by a delete. Not a failure, and no row is
        // invented for a person who is already gone.
        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Ghost']]);
        $this->idmVanished[] = self::PERSON;

        $event = $this->event('create');
        $this->process($event);

        $this->assertSame(IdpWebhookEvent::STATUS_SKIPPED, $event->fresh()->status);
        $this->assertSame('user_not_found_upstream', $event->fresh()->error);
        $this->assertNull($this->syncedUser());
    }

    public function test_does_not_reprocess_an_already_processed_event(): void
    {
        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Once']]);
        $event = $this->event('create');

        $this->process($event);
        $this->process($event);

        $this->assertSame(1, $event->fresh()->attempts);
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function process(IdpWebhookEvent $event): void
    {
        $this->app->call([new ProcessIdpWebhookEvent($event->id), 'handle']);
    }

    private function event(string $action, string $personId = self::PERSON, array $properties = []): IdpWebhookEvent
    {
        return IdpWebhookEvent::create([
            'provider' => 'eduplaces',
            'entity_type' => IdpEvent::ENTITY_USER,
            'action' => $action,
            'entity_id' => $personId,
            'updated_properties' => $properties,
            'payload' => ['event' => 'person', 'action' => $action, 'personId' => $personId],
            'status' => IdpWebhookEvent::STATUS_PENDING,
            'received_at' => now(),
        ]);
    }

    /**
     * Stand in for the IDM, reading from mutable state.
     *
     * A closure rather than a URL map because Http::fake() merges successive
     * calls instead of replacing them — restubbing the same URL leaves the
     * first response winning, which silently hides every second step of a
     * multi-step test.
     */
    private function fakeIdm(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                str_ends_with($path, '/oauth2/token') => Http::response([
                    'access_token' => 'token', 'token_type' => 'bearer', 'expires_in' => 3599,
                ]),
                (bool) preg_match('#/schools/[^/]+/(people|users)$#', $path) => Http::response(
                    array_values($this->idmPeople),
                ),
                (bool) preg_match('#/schools/[^/]+/groups$#', $path) => Http::response([]),
                (bool) preg_match('#/(people|users)/([^/]+)$#', $path, $m) => $this->personResponse(urldecode($m[2])),
                default => Http::response(status: 404),
            };
        });
    }

    /**
     * Put a person into the IDM stand-in, replacing any earlier version.
     *
     * @param  array<string, mixed>  $person
     */
    private function setPerson(array $person, string $personId = self::PERSON): void
    {
        $this->idmPeople[$personId] = array_merge(['id' => $personId, 'groups' => []], $person);
    }

    private function personResponse(string $personId): PromiseInterface
    {
        return isset($this->idmPeople[$personId]) && ! in_array($personId, $this->idmVanished, true)
            ? Http::response($this->idmPeople[$personId])
            : Http::response(status: 404);
    }

    private function syncedUser(string $personId = self::PERSON): ?LegacyUser
    {
        return self::$testTenant->run(
            fn () => LegacyUser::where('idp_user_id', $personId)->first(),
        );
    }

    private function seedLocalRoom(?string $eduplacesId, string $name): int
    {
        return (int) self::$testTenant->run(fn () => DB::table('au_rooms')->insertGetId([
            'room_name' => $name,
            'status' => 1,
            'type' => 0,
            'hash_id' => md5($name.microtime(true)),
            'idp_group_id' => $eduplacesId,
        ]));
    }

    private function clearSyncedUsers(): void
    {
        self::$testTenant->run(function () {
            $ids = LegacyUser::whereNotNull('idp_user_id')->pluck('id')->all();

            if ($ids !== []) {
                DB::table('au_rel_rooms_users')->whereIn('user_id', $ids)->delete();
                DB::table('au_rel_rooms_users')->whereIn('user_id', $ids)->delete();
                LegacyUser::whereIn('id', $ids)->delete();
            }

            DB::table('au_rooms')->whereNotNull('idp_group_id')->delete();
            DB::table('au_rooms')->whereIn('room_name', ['Schülerzeitung'])->delete();
        });
    }
}
