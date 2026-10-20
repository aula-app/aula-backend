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
        DB::transaction(function () use ($legacyRoom) {
            $legacyRoom->deleteOrFail();
            // TODO add a test
            DB::table('au_rel_rooms_users')->where('room_id', $legacyRoom->id)->delete();
        });
        // TODO remove room from the JSONs of affected users -- see the huge query im Room.php:740
    }
}
