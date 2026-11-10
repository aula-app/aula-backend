<?php declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\LegacyRoom;
use Tests\Concerns\CreatesTestTenant;
use Illuminate\Support\Facades\DB;

trait CreatesTestRooms
{
    use CreatesTestTenant;

    private static ?LegacyRoom $testRoom = null;
    private static ?LegacyRoom $testRoom2 = null;

    protected function ensureTestRoomsExist(): void
    {
        $this->ensureTestTenantExists();

        self::$testTenant->run(function () {
            self::$testRoom = LegacyRoom::where('hash_id', md5('Testroom'))->first()
                ?? new LegacyRoom();
            self::$testRoom->room_name = 'Testroom';
            self::$testRoom->hash_id = md5('Testroom');
            self::$testRoom->status = 1;
            self::$testRoom->description_public = 'Public test description';
            self::$testRoom->description_internal = 'Internal test description';
            self::$testRoom->phase_duration_1 = 14;
            self::$testRoom->phase_duration_3 = 14;
            self::$testRoom->save();
            // would probably also have to update users' roles json to be super clean...
            DB::table('au_rel_rooms_users')->where('room_id', self::$testRoom->id)->delete();

            self::$testRoom2 = LegacyRoom::where('hash_id', md5('Testroom2'))->first()
                ?? new LegacyRoom();
            self::$testRoom2->room_name = 'Testroom 2';
            self::$testRoom2->hash_id = md5('Testroom2');
            self::$testRoom2->status = 1;
            self::$testRoom2->description_public = 'Public test description';
            self::$testRoom2->description_internal = 'Internal test description';
            self::$testRoom2->phase_duration_1 = 14;
            self::$testRoom2->phase_duration_3 = 14;
            self::$testRoom2->save();
            DB::table('au_rel_rooms_users')->where('room_id', self::$testRoom2->id)->delete();
        });
    }

    protected function tearDownTestRooms(): void
    {
        self::$testTenant->run(function () {
            LegacyRoom::whereIn(
                'hash_id',
                [md5('Testroom'), md5('Testroom2')]
            )->delete();
        });
    }
}
