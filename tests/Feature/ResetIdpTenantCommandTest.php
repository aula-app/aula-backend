<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\IdpDirectoryEntry;
use App\Models\LegacyUser;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * `idp:reset-tenant` puts a tenant back to never-synced so the first-login
 * bootstrap can be exercised again. It has to undo the import without taking
 * anything else with it.
 */
class ResetIdpTenantCommandTest extends TestCase
{
    use CreatesTestTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        self::$testTenant->refresh();
        self::$testTenant->update([
            'idp_school_id' => 'school-reset-test',
            'sso_provider' => 'eduplaces',
            'idp_import_status' => 'completed',
            'idp_import_rooms' => 1,
            'idp_import_users' => 1,
        ]);

        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        Tenant::where('id', self::$testTenant->id)->update([
            'idp_school_id' => null,
            'idp_import_status' => null,
        ]);
        parent::tearDown();
    }

    public function test_it_deletes_what_the_import_created(): void
    {
        [$roomId, $importedId] = $this->seedImportedSchool();

        $this->artisan('idp:reset-tenant', ['instance_code' => 'TEST001', '--force' => true])
            ->assertExitCode(0);

        self::$testTenant->run(function () use ($roomId, $importedId) {
            $this->assertSame(0, DB::table('au_rooms')->where('id', $roomId)->count());
            $this->assertSame(0, LegacyUser::where('id', $importedId)->count());
            $this->assertSame(0, DB::table('au_rel_rooms_users')->where('room_id', $roomId)->count());
        });
    }

    public function test_it_keeps_accounts_that_predate_the_import(): void
    {
        $this->seedImportedSchool();
        $nativeId = $this->seedNativeUser();

        $this->artisan('idp:reset-tenant', ['instance_code' => 'TEST001', '--force' => true]);

        self::$testTenant->run(function () use ($nativeId) {
            $native = LegacyUser::find($nativeId);

            // A reset of the directory sync must not delete a school's own
            // accounts, only clear the identity the sync attached to them.
            $this->assertNotNull($native);
            $this->assertNull($native->sso_sub);
            $this->assertNull($native->idp_user_id);
        });
    }

    public function test_it_clears_the_signal_that_makes_a_login_the_first(): void
    {
        $this->seedImportedSchool();

        $this->artisan('idp:reset-tenant', ['instance_code' => 'TEST001', '--force' => true]);

        $tenant = self::$testTenant->fresh();

        $this->assertNull($tenant->idp_school_id);
        $this->assertNull($tenant->idp_import_status);
        $this->assertSame(0, self::$testTenant->run(
            fn () => LegacyUser::whereNotNull('sso_sub')->count(),
        ));
    }

    public function test_it_leaves_the_tenant_able_to_bootstrap_again(): void
    {
        $this->seedImportedSchool();
        Tenant::where('id', self::$testTenant->id)
            ->update(['idp_migration_status' => Tenant::IDP_MIGRATION_CONNECTED]);

        $this->artisan('idp:reset-tenant', ['instance_code' => 'TEST001', '--force' => true]);

        // A tenant still part-way through a migration refuses to bootstrap on a
        // first login, which is exactly what this command exists to set up.
        $tenant = self::$testTenant->fresh();
        $this->assertNull($tenant->idp_migration_status);
        $this->assertFalse($tenant->isMigratingToIdp());
    }

    public function test_it_discards_a_merge_proposal(): void
    {
        $this->seedImportedSchool();
        self::$testTenant->run(fn () => DB::table('idp_merge_candidates')->insert([
            'kind' => 'user',
            'idp_id' => 'person-stale',
            'idp_name' => 'Stale',
            'idp_name_kind' => 'real',
            'local_id' => null,
            'local_name' => null,
            'outcome' => 'none',
            'decision' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $this->artisan('idp:reset-tenant', ['instance_code' => 'TEST001', '--force' => true]);

        // The proposal describes rows the reset has just deleted.
        $this->assertSame(0, self::$testTenant->run(
            fn () => DB::table('idp_merge_candidates')->count(),
        ));
    }

    public function test_it_strips_roles_only_for_rooms_it_deleted(): void
    {
        [$roomId] = $this->seedImportedSchool();

        $nativeRoomHash = md5('native-room'.microtime(true));
        $userId = self::$testTenant->run(function () use ($roomId, $nativeRoomHash) {
            $importedHash = (string) DB::table('au_rooms')->where('id', $roomId)->value('hash_id');
            DB::table('au_rooms')->insert([
                'room_name' => 'Schülerzeitung', 'status' => 1, 'type' => 0, 'hash_id' => $nativeRoomHash,
            ]);

            $user = new LegacyUser;
            $user->username = 'reset.roles.'.random_int(1000, 999999);
            $user->displayname = $user->username;
            $user->status = UserStatus::Active;
            $user->userlevel = 20;
            $user->hash_id = md5($user->username);
            $user->roles = json_encode([
                ['role' => 20, 'room' => $importedHash],
                ['role' => 30, 'room' => $nativeRoomHash],
            ]);
            $user->save();

            return $user->id;
        });

        $this->artisan('idp:reset-tenant', ['instance_code' => 'TEST001', '--force' => true]);

        self::$testTenant->run(function () use ($userId, $nativeRoomHash) {
            $roles = json_decode((string) LegacyUser::find($userId)->roles, true);

            // The aula-native room role is somebody's real configuration.
            $this->assertSame([['role' => 30, 'room' => $nativeRoomHash]], $roles);
        });
    }

    public function test_it_clears_the_directory_index_for_that_tenant_only(): void
    {
        $this->seedImportedSchool();

        IdpDirectoryEntry::create([
            'provider' => 'eduplaces', 'entity_type' => IdpDirectoryEntry::TYPE_USER,
            'idp_id' => 'person-elsewhere', 'tenant_id' => 'some-other-tenant',
        ]);

        $this->artisan('idp:reset-tenant', ['instance_code' => 'TEST001', '--force' => true]);

        $this->assertSame(0, IdpDirectoryEntry::where('tenant_id', self::$testTenant->id)->count());
        // Another tenant's index is none of this reset's business.
        $this->assertSame(1, IdpDirectoryEntry::where('tenant_id', 'some-other-tenant')->count());
    }

    public function test_it_does_nothing_without_confirmation(): void
    {
        [, $importedId] = $this->seedImportedSchool();

        $this->artisan('idp:reset-tenant', ['instance_code' => 'TEST001'])
            ->expectsConfirmation('Reset TEST001? Imported users and rooms will be deleted.', 'no')
            ->assertExitCode(0);

        self::$testTenant->run(function () use ($importedId) {
            $this->assertNotNull(LegacyUser::find($importedId));
        });
        $this->assertSame('school-reset-test', self::$testTenant->fresh()->idp_school_id);
    }

    public function test_it_fails_on_an_unknown_instance_code(): void
    {
        $this->artisan('idp:reset-tenant', ['instance_code' => 'NOPE1', '--force' => true])
            ->assertExitCode(1);
    }

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * @return array{0: int, 1: int} room id, imported user id
     */
    private function seedImportedSchool(): array
    {
        return self::$testTenant->run(function (): array {
            $hash = md5('imported-room'.microtime(true));
            $roomId = (int) DB::table('au_rooms')->insertGetId([
                'room_name' => 'Klasse 5a', 'status' => 1, 'type' => 0,
                'hash_id' => $hash, 'idp_group_id' => 'group-reset-1',
            ]);

            $user = new LegacyUser;
            $user->username = 'reset.imported.'.random_int(1000, 999999);
            $user->displayname = $user->username;
            $user->idp_user_id = 'person-reset-1';
            $user->status = UserStatus::Active;
            $user->userlevel = 20;
            $user->hash_id = md5($user->username);
            $user->save();

            DB::table('au_rel_rooms_users')->insert([
                'room_id' => $roomId, 'user_id' => $user->id, 'status' => 1, 'updater_id' => 0,
            ]);

            IdpDirectoryEntry::create([
                'provider' => 'eduplaces', 'entity_type' => IdpDirectoryEntry::TYPE_USER,
                'idp_id' => 'person-reset-1', 'tenant_id' => self::$testTenant->id,
            ]);

            return [$roomId, (int) $user->id];
        });
    }

    private function seedNativeUser(): int
    {
        return (int) self::$testTenant->run(function () {
            $user = new LegacyUser;
            $user->username = 'reset.native.'.random_int(1000, 999999);
            $user->displayname = $user->username;
            $user->email = 'reset.native@test.example';
            $user->pw = password_hash('secret', PASSWORD_BCRYPT);
            // Signed in via SSO at some point, but not created by the import.
            $user->sso_sub = 'kc-sub-native';
            $user->status = UserStatus::Active;
            $user->userlevel = 20;
            $user->hash_id = md5($user->username);
            $user->save();

            return $user->id;
        });
    }

    private function clean(): void
    {
        self::$testTenant->run(function () {
            $ids = LegacyUser::where('username', 'like', 'reset.%')->pluck('id')->all();

            if ($ids !== []) {
                DB::table('au_rel_rooms_users')->whereIn('user_id', $ids)->delete();
                LegacyUser::whereIn('id', $ids)->delete();
            }

            $roomIds = DB::table('au_rooms')
                ->whereNotNull('idp_group_id')
                ->orWhere('room_name', 'Schülerzeitung')
                ->pluck('id')->all();

            if ($roomIds !== []) {
                DB::table('au_rel_rooms_users')->whereIn('room_id', $roomIds)->delete();
                DB::table('au_rooms')->whereIn('id', $roomIds)->delete();
            }
        });

        IdpDirectoryEntry::query()->delete();
    }
}
