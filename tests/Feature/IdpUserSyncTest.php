<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Jobs\ProcessIdpWebhookEvent;
use App\Models\IdpWebhookEvent;
use App\Models\LegacyUser;
use App\Services\Idp\Dto\IdpEvent;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * End to end for `person` events: ProcessIdpWebhookEvent resolves the tenant,
 * UserSync reads the user back from the directory, and SchoolImport::importUser()
 * converges the local row onto it.
 */
class IdpUserSyncTest extends TestCase
{
    use CreatesTestTenant;

    private const string API_URL = 'https://api.eduplaces.test';
    private const string AUTH_URL = 'https://auth.eduplaces.test';
    private const string SCHOOL = 'school-person-sync';
    private const string PERSON = 'person-sync-1';

    /** @var array<string, array<string, mixed>> the users the IDM stand-in holds */
    private array $idmPeople = [];

    /** @var list<string> listed at the school, but 404 on individual lookup */
    private array $idmVanished = [];

    /** @var array<string, array<string, mixed>> the groups the IDM stand-in holds, with members */
    private array $idmGroups = [];

    /** Off by default: `/schools/{id}` 404s and the school-wide group reconcile is skipped. */
    private bool $schoolVisible = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        // Shared across test classes, so the in-memory copy goes stale and
        // Eloquent would skip writes it wrongly believes are no-ops.
        self::$testTenant->refresh();
        self::$testTenant->update(['idp_school_id' => self::SCHOOL, 'sso_provider' => 'eduplaces']);
        self::$testTenant->run(function () {
            DB::table('au_rooms')->insert([
                    'room_name' => 'Schule',
                    'description_internal' => null,
                    'hash_id' => Str::random(30),
                    'status' => 1,
                    'type' => 1,
                ]);
        });

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
        $this->clearSyncedUsers();

        $this->idmPeople = [];
        $this->idmVanished = [];
        $this->idmGroups = [];
        $this->schoolVisible = false;
        $this->fakeIdm();
    }

    protected function tearDown(): void
    {
        $this->clearSyncedUsers();
        IdpWebhookEvent::query()->delete();
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
        // The directory exposes no address, so `email` stays null until a login
        // supplies one.
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
    }

    public function test_keeps_the_userlevel_when_the_role_changes_upstream(): void
    {
        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Promoted']]);
        $this->process($this->event('create'));
        $this->assertSame(20, $this->syncedUser()->userlevel->value);

        $this->setPerson(['role' => 'TEACHER', 'name' => ['last' => 'Promoted']]);
        $this->process($this->event('update', properties: ['role']));

        // userlevel is set from the role once, on creation; aula owns it after.
        $this->assertSame(20, $this->syncedUser()->userlevel->value);
    }

    public function test_keeps_a_userlevel_changed_inside_aula(): void
    {
        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Moderator']]);
        $this->process($this->event('create'));

        self::$testTenant->run(fn () => LegacyUser::where('idp_user_id', self::PERSON)->update(['userlevel' => 45]));

        $this->process($this->event('update', properties: ['name']));

        $this->assertSame(45, $this->syncedUser()->userlevel->value);
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

        // Eduplaces documents no idempotency key, so a redelivered event has to
        // be inert.
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

        // The legacy ideas, votes and comments tables reference the row, so a
        // delete archives instead.
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

        // No read-back on ACTION_DELETE: the payload carries all UserSync
        // needs.
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
        // The event resolves to a tenant, but no row carries this
        // idp_user_id, so there is nothing to archive.
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

    public function test_creates_the_room_for_a_group_aula_does_not_have_yet(): void
    {
        $this->setPerson([
            'role' => 'STUDENT',
            'name' => ['firstFull' => 'New', 'last' => 'Member'],
            'groups' => [['id' => 'group-new', 'name' => 'Aula Group']],
        ]);
        $this->setGroup('group-new', 'Aula Group', [
            ['id' => self::PERSON, 'role' => 'STUDENT'],
        ]);

        $this->process($this->event('update', properties: ['groups']));

        self::$testTenant->run(function () {
            $room = DB::table('au_rooms')->where('idp_group_id', 'group-new')->first();
            $this->assertNotNull($room);
            $this->assertSame('Aula Group', $room->room_name);

            $user = LegacyUser::where('idp_user_id', self::PERSON)->firstOrFail();
            $this->assertSame(1, DB::table('au_rel_rooms_users')
                ->where('user_id', $user->id)->where('room_id', $room->id)->count());
        });
    }

    public function test_imports_every_member_of_a_newly_created_room(): void
    {
        $this->seedLocalUser('person-already-local', 'Local');

        $this->setPerson([
            'role' => 'STUDENT',
            'name' => ['last' => 'Trigger'],
            'groups' => [['id' => 'group-new', 'name' => 'Aula Group']],
        ]);
        $this->setPerson([
            'role' => 'TEACHER',
            'name' => ['firstFull' => 'Not', 'last' => 'Imported'],
            'groups' => [['id' => 'group-new', 'name' => 'Aula Group']],
        ], 'person-not-local');
        $this->setPerson([
            'role' => 'STUDENT',
            'name' => ['last' => 'Local'],
            'groups' => [['id' => 'group-new', 'name' => 'Aula Group']],
        ], 'person-already-local');
        $this->setGroup('group-new', 'Aula Group', [
            ['id' => self::PERSON, 'role' => 'STUDENT'],
            ['id' => 'person-not-local', 'role' => 'TEACHER'],
            ['id' => 'person-already-local', 'role' => 'STUDENT'],
        ]);

        $this->process($this->event('update', properties: ['groups']));

        self::$testTenant->run(function () {
            $roomId = DB::table('au_rooms')->where('idp_group_id', 'group-new')->value('id');

            $members = DB::table('au_rel_rooms_users')
                ->join('au_users_basedata', 'au_users_basedata.id', '=', 'au_rel_rooms_users.user_id')
                ->where('room_id', $roomId)
                ->pluck('au_users_basedata.idp_user_id')
                ->sort()->values()->all();

            $this->assertSame(['person-already-local', 'person-not-local', self::PERSON], $members);

            $imported = LegacyUser::where('idp_user_id', 'person-not-local')->firstOrFail();
            $this->assertSame('Not Imported', $imported->realname);
            $this->assertSame(40, $imported->userlevel->value);
        });
    }

    public function test_refreshes_every_group_of_the_person_on_any_update(): void
    {
        $roomId = $this->seedLocalRoom('group-known', 'Klasse 5a');

        $this->setPerson([
            'role' => 'STUDENT',
            'name' => ['last' => 'Pinger'],
            'groups' => [['id' => 'group-known', 'name' => 'Klasse 6a']],
        ]);
        $this->setPerson([
            'role' => 'STUDENT',
            'name' => ['last' => 'Classmate'],
            'groups' => [['id' => 'group-known', 'name' => 'Klasse 6a']],
        ], 'person-classmate');
        $this->setGroup('group-known', 'Klasse 6a', [
            ['id' => self::PERSON, 'role' => 'STUDENT'],
            ['id' => 'person-classmate', 'role' => 'STUDENT'],
        ]);

        // Not a groups change: any person event refreshes the person's groups.
        $this->process($this->event('update', properties: ['name']));

        self::$testTenant->run(function () use ($roomId) {
            $this->assertSame('Klasse 6a', DB::table('au_rooms')->where('id', $roomId)->value('room_name'));

            $members = DB::table('au_rel_rooms_users')
                ->join('au_users_basedata', 'au_users_basedata.id', '=', 'au_rel_rooms_users.user_id')
                ->where('room_id', $roomId)
                ->pluck('au_users_basedata.idp_user_id')
                ->sort()->values()->all();

            $this->assertSame(['person-classmate', self::PERSON], $members);
        });
    }

    public function test_still_syncs_the_user_when_the_new_group_is_gone_upstream(): void
    {
        $this->setPerson([
            'role' => 'STUDENT',
            'name' => ['last' => 'Orphan'],
            'groups' => [['id' => 'group-gone', 'name' => 'Gone']],
        ]);

        $event = $this->event('update', properties: ['groups']);
        $this->process($event);

        $this->assertSame(IdpWebhookEvent::STATUS_PROCESSED, $event->fresh()->status);
        $this->assertNotNull($this->syncedUser());
        self::$testTenant->run(fn () => $this->assertFalse(
            DB::table('au_rooms')->where('idp_group_id', 'group-gone')->exists(),
        ));
    }

    public function test_leaves_aula_native_group_memberships_alone(): void
    {
        // A room created inside aula carries no idp_group_id, so
        // syncUserRooms() leaves it alone.
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
    // School-wide group reconcile
    // =========================================================

    public function test_creates_rooms_for_listed_groups_no_person_names(): void
    {
        $this->schoolVisible = true;
        $this->setGroup('group-empty', 'Leere Gruppe', []);
        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Unrelated'], 'groups' => []]);

        $this->process($this->event('update', properties: ['name']));

        self::$testTenant->run(fn () => $this->assertSame(
            'Leere Gruppe',
            DB::table('au_rooms')->where('idp_group_id', 'group-empty')->where('status', 1)->value('room_name'),
        ));
    }

    public function test_archives_and_empties_rooms_for_groups_no_longer_listed(): void
    {
        $roomId = $this->seedLocalRoom('group-deleted', 'Gelöscht');
        $this->seedLocalUser('person-left-behind', 'Behind');
        $this->schoolVisible = true;

        self::$testTenant->run(function () use ($roomId) {
            $userId = LegacyUser::where('idp_user_id', 'person-left-behind')->value('id');
            $hashId = DB::table('au_rooms')->where('id', $roomId)->value('hash_id');

            DB::table('au_rel_rooms_users')->insert(['room_id' => $roomId, 'user_id' => $userId, 'status' => 1, 'updater_id' => 0]);
            DB::table('au_users_basedata')->where('id', $userId)->update(['roles' => json_encode([['role' => 20, 'room' => $hashId]])]);
        });

        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Trigger'], 'groups' => []]);
        $this->process($this->event('update', properties: ['groups']));

        self::$testTenant->run(function () use ($roomId) {
            $room = DB::table('au_rooms')->where('id', $roomId)->first();
            $userId = LegacyUser::where('idp_user_id', 'person-left-behind')->value('id');

            $this->assertSame(3, (int) $room->status);
            $this->assertSame(0, DB::table('au_rel_rooms_users')->where('room_id', $roomId)->count());
            $this->assertStringNotContainsString(
                (string) $room->hash_id,
                (string) DB::table('au_users_basedata')->where('id', $userId)->value('roles'),
            );
        });
    }

    public function test_reactivates_an_archived_room_whose_group_is_listed_again(): void
    {
        $this->schoolVisible = true;
        $roomId = $this->seedLocalRoom('group-back', 'Zurück');
        self::$testTenant->run(fn () => DB::table('au_rooms')->where('id', $roomId)->update(['status' => 3]));

        $this->setGroup('group-back', 'Zurück', []);
        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Trigger'], 'groups' => []]);
        $this->process($this->event('update', properties: ['name']));

        self::$testTenant->run(fn () => $this->assertSame(
            1,
            (int) DB::table('au_rooms')->where('id', $roomId)->value('status'),
        ));
    }

    public function test_archives_nothing_when_the_school_is_not_visible(): void
    {
        // A school disconnected from the app lists no groups. That must not
        // read as "every group was deleted".
        $roomId = $this->seedLocalRoom('group-kept', 'Bleibt');

        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => 'Trigger'], 'groups' => []]);
        $this->process($this->event('update', properties: ['name']));

        self::$testTenant->run(fn () => $this->assertSame(
            1,
            (int) DB::table('au_rooms')->where('id', $roomId)->value('status'),
        ));
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
        // Somehow we receive the same personId, but coming from another school that we don't integrate with
        $event = $this->event('update', $this::PERSON, schoolId: 'another-school');
        $this->process($event);

        $this->assertSame(IdpWebhookEvent::STATUS_SKIPPED, $event->fresh()->status);
        $this->assertSame('tenant_unresolved', $event->fresh()->error);
    }

    public function test_skips_when_the_person_vanished_upstream(): void
    {
        // Listed at the school, so the tenant resolves, but the read-back 404s,
        // as after a create followed by a delete. Not a failure, and no row is
        // created for a directory user that no longer exists.
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

    private function event(string $action, string $personId = self::PERSON, array $properties = [], string $schoolId = self::SCHOOL): IdpWebhookEvent
    {
        return IdpWebhookEvent::create([
            'provider' => 'eduplaces',
            'entity_type' => IdpEvent::ENTITY_USER,
            'action' => $action,
            'entity_id' => $personId,
            'updated_properties' => $properties,
            'payload' => ['event' => 'person', 'action' => $action, 'personId' => $personId, 'schoolId' => $schoolId],
            'status' => IdpWebhookEvent::STATUS_PENDING,
            'received_at' => now(),
        ]);
    }

    /**
     * Stand in for the IDM, reading from mutable state.
     *
     * A closure rather than a URL map: Http::fake() merges successive calls
     * instead of replacing them, so restubbing a URL leaves the first response
     * matching and hides the second step of a multi-step test.
     */
    private function fakeIdm(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                str_ends_with($path, '/oauth2/token') => Http::response([
                    'access_token' => 'token', 'token_type' => 'bearer', 'expires_in' => 3599,
                ]),
                (bool) preg_match('#/schools/[^/]+/people$#', $path) => Http::response(
                    array_values($this->idmPeople),
                ),
                (bool) preg_match('#/schools/[^/]+/groups$#', $path) => Http::response($this->schoolVisible
                    ? array_values(array_map(fn (array $g): array => ['id' => $g['id'], 'name' => $g['name']], $this->idmGroups))
                    : []),
                (bool) preg_match('#/schools/([^/]+)$#', $path, $m) => $this->schoolVisible
                    ? Http::response(['id' => urldecode($m[1]), 'name' => 'Schule'])
                    : Http::response(status: 404),
                (bool) preg_match('#/people/([^/]+)$#', $path, $m) => $this->personResponse(urldecode($m[1])),
                (bool) preg_match('#/groups/([^/]+)$#', $path, $m) => isset($this->idmGroups[urldecode($m[1])])
                    ? Http::response($this->idmGroups[urldecode($m[1])])
                    : Http::response(status: 404),
                default => Http::response(status: 404),
            };
        });
    }

    /**
     * Put a user into the IDM stand-in, replacing any earlier version.
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

    /**
     * @param  list<array<string, mixed>>  $members
     */
    private function setGroup(string $groupId, string $name, array $members): void
    {
        $this->idmGroups[$groupId] = ['id' => $groupId, 'name' => $name, 'status' => 'ACTIVE', 'members' => $members];
    }

    private function seedLocalUser(string $personId, string $lastName): void
    {
        $this->setPerson(['role' => 'STUDENT', 'name' => ['last' => $lastName]], $personId);
        $this->process($this->event('create', $personId));
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
