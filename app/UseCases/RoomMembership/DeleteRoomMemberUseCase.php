<?php

namespace App\UseCases\RoomMembership;

use App\Enums\Gates;
use App\Models\LegacyRoom;
use Illuminate\Support\Facades\Gate;

class DeleteRoomMemberUseCase
{
    public function execute(string $roomPublicId, string $userPublicId): void
    {
        Gate::authorize(Gates::DeleteRoomMember);

        $legacyRoom = LegacyRoom::with('users:hash_id')
            ->where('hash_id', $roomPublicId)
            ->withWhereHas('users', fn($q) => $q->where('hash_id', $userPublicId))
            ->firstOrFail();
        $attachedUserId = $legacyRoom->users[0]->id;
        $legacyRoom->users()->detach($attachedUserId);
    }
}

