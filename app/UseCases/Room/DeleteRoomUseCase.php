<?php

declare(strict_types=1);

namespace App\UseCases\Room;

use App\Enums\Gates;
use App\Models\LegacyRoom;
use App\Relations\RoomMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteRoomUseCase
{
    public function execute(string $publicId): void
    {
        Gate::authorize(Gates::DeleteRoom);
        $legacyRoom = LegacyRoom::where('hash_id', $publicId)->sole(['id']);
        DB::transaction(function () use ($legacyRoom, $publicId) {
            $legacyRoom->deleteOrFail();
            // TODO add a test
            DB::table('au_rel_rooms_users')->where('room_id', $legacyRoom->id)->delete();
            // remove room from the JSONs of affected users to keep legacy in sync
            // query copied from legacy Room.php as temporary shim
            $remove_roles_in_room_query = <<<EOD
                UPDATE au_users_basedata u1
                LEFT JOIN (
                    SELECT
                        u.id,
                        JSON_ARRAYAGG(
                            JSON_OBJECT('room', jt.room_val, 'role', jt.role_val)
                        ) AS new_roles
                    FROM au_users_basedata u
                    JOIN JSON_TABLE(
                        u.roles,
                        '$[*]' COLUMNS(
                            room_val VARCHAR(255) PATH '$.room',
                            role_val INT(11) PATH '$.role'
                        )
                    ) AS jt
                    WHERE jt.room_val != ?
                    GROUP BY u.id
                ) u2 ON u1.id = u2.id
                SET u1.roles = COALESCE(u2.new_roles, JSON_ARRAY())
                WHERE JSON_SEARCH(u1.roles, 'one', ?, NULL, '$[*].room') IS NOT NULL
            EOD;
            DB::statement($remove_roles_in_room_query, [$publicId, $publicId]);
        });
    }
}
