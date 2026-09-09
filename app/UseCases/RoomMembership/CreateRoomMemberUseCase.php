<?php

namespace App\UseCases\RoomMembership;

use App\Data\RoomMembership\DomainRoomMemberData;
use App\Data\RoomMembership\Requests\StoreRoomMemberData;
use App\Enums\Gates;
use App\Models\LegacyRoom;
use App\Models\LegacyUser;
use Illuminate\Support\Facades\Gate;

class CreateRoomMemberUseCase
{
    public function execute(string $roomPublicId, string $userPublicId, StoreRoomMemberData $storeRoomMemberData): DomainRoomMemberData
    {
        Gate::authorize(Gates::CreateRoomMember);

        $legacyRoom = LegacyRoom::where('hash_id', $roomPublicId)->firstOrFail(['id']);
        $legacyUser = LegacyUser::where('hash_id', $userPublicId)->firstOrFail(['id']);
        if ($legacyRoom->users->contains($legacyUser->id)) {
            $legacyRoom->users()->updateExistingPivot($legacyUser->id, $storeRoomMemberData->toArray());
        } else {
            $legacyRoom->users()->attach($legacyUser->id, $storeRoomMemberData->toArray());
        }

        // update legacy json field
        $legacyUser->updateRolesJson();
        $legacyUser->saveOrFail();

        return new DomainRoomMemberData(
            $roomPublicId,
            $userPublicId,
            $storeRoomMemberData->roomUserLevel,
        );
    }
}
