<?php

namespace App\UseCases\RoomUser;

use App\Models\LegacyRoom;

class DeleteRoomUserUseCase
{
    public function execute(string $roomPublicId, string $userPublicId): void
    {
        $legacyRoom = LegacyRoom::with('users:hash_id')
            ->where('hash_id', $roomPublicId)
            ->withWhereHas('users', fn($q) => $q->where('hash_id', $userPublicId))
            ->firstOrFail();
        $attachedUserId = $legacyRoom->users[0]->id;
        $legacyRoom->users()->detach($attachedUserId);
    }
}

