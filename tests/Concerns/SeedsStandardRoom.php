<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Gives the test tenant its standard room (au_rooms.type = 1).
 *
 * tenants:migrate creates none. Requires CreatesTestTenant.
 */
trait SeedsStandardRoom
{
    private const string SEEDED_STANDARD_ROOM_HASH = 'phpunit-seeded-standard-room';

    /**
     * @return array{id: int, name: string}
     */
    private function standardRoom(): array
    {
        return self::$testTenant->run(function (): array {
            $room = DB::table('au_rooms')->where('type', 1)->first(['id', 'room_name']);

            if ($room !== null) {
                return ['id' => (int) $room->id, 'name' => (string) $room->room_name];
            }

            $id = (int) DB::table('au_rooms')->insertGetId([
                'room_name' => 'Schule', 'status' => 1, 'type' => 1,
                'hash_id' => self::SEEDED_STANDARD_ROOM_HASH,
            ]);

            return ['id' => $id, 'name' => 'Schule'];
        });
    }

    private function removeSeededStandardRoom(): void
    {
        self::$testTenant->run(function (): void {
            $ids = DB::table('au_rooms')->where('hash_id', self::SEEDED_STANDARD_ROOM_HASH)->pluck('id')->all();

            if ($ids !== []) {
                DB::table('au_rel_rooms_users')->whereIn('room_id', $ids)->delete();
                DB::table('au_rooms')->whereIn('id', $ids)->delete();
            }
        });
    }
}
