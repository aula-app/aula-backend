<?php declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\LegacyRoom;
use Tests\Concerns\CreatesTestTenant;

trait CreatesTestRoom
{
    use CreatesTestTenant;

    private static ?LegacyRoom $testRoom = null;

    protected function ensureTestRoomExists(): void
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
        });
    }
}
