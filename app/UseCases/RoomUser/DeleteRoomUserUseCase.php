<?php

namespace App\UseCases\RoomUser;

use App\Enums\Gates;
use App\Models\LegacyRoom;
use Illuminate\Support\Facades\Gate;

class DeleteRoomUserUseCase
{
    public function execute(string $roomPublicId, string $userPublicId): void
    {
        Gate::authorize(Gates::DeleteRoomUser);

        $legacyRoom = LegacyRoom::with('users:hash_id')
            ->where('hash_id', $roomPublicId)
            ->withWhereHas('users', fn($q) => $q->where('hash_id', $userPublicId))
            ->firstOrFail();
        $attachedUserId = $legacyRoom->users[0]->id;
        $legacyRoom->users()->detach($attachedUserId);
    }
}

