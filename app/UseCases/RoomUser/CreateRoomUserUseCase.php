<?php

namespace App\UseCases\RoomUser;

use App\Data\RoomUser\DomainRoomUserData;
use App\Data\RoomUser\Requests\StoreRoomUserData;
use App\Enums\Gates;
use App\Models\LegacyRoom;
use App\Models\LegacyUser;
use App\Relations\RoomUser;
use Illuminate\Support\Facades\Gate;

class CreateRoomUserUseCase
{
    public function execute(string $roomPublicId, string $userPublicId, StoreRoomUserData $storeRoomUserData): DomainRoomUserData
    {
        Gate::authorize(Gates::CreateRoomUser);

        $legacyRoom = LegacyRoom::where('hash_id', $roomPublicId)->firstOrFail(['id']);
        $legacyUser = LegacyUser::where('hash_id', $userPublicId)->firstOrFail(['id']);
        if ($legacyRoom->users->contains($legacyUser->id)) {
            $legacyRoom->users()->updateExistingPivot($legacyUser->id, $storeRoomUserData->toArray());
        } else {
            $legacyRoom->users()->attach($legacyUser->id, $storeRoomUserData->toArray());
        }

        // update legacy json field
        $legacyRoles = $legacyUser->rooms->map(fn($r) => [
            "room" => $r->hash_id,
            "role" => $r->pivot->room_user_level,
        ]);
        $legacyUser->roles = json_encode($legacyRoles);
        $legacyUser->saveOrFail();

        return new DomainRoomUserData(
            $roomPublicId,
            $userPublicId,
            $storeRoomUserData->roomUserLevel,
        );
    }
}
