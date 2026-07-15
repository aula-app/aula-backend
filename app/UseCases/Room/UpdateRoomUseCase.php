<?php

declare(strict_types=1);

namespace App\UseCases\Room;

use App\Data\Room\DomainRoomData;
use App\Data\Room\Requests\UpdateRoomData;
use App\Models\LegacyRoom;
use Illuminate\Support\Facades\Gate;

class UpdateRoomUseCase
{
    public function execute(string $hashId, UpdateRoomData $userUpdateData): DomainRoomData
    {
        // TODO
        Gate::authorize('admin');
        $legacyRoom = LegacyRoom::where('hash_id', $hashId)->firstOrFail();

        $legacyRoom->room_name = $storeRoomData->name;
        $legacyRoom->status = $storeRoomData->status;
        $legacyRoom->description_public = $storeRoomData->descriptionPublic;
        $legacyRoom->description_internal = $storeRoomData->descriptionInternal;
        $legacyRoom->phase_duration_1 = $storeRoomData->phaseDuration1;
        $legacyRoom->phase_duration_3 = $storeRoomData->phaseDuration3;

        $legacyRoom->save();
        $legacyRoom->refresh();
        return DomainRoomData::from($legacyRoom);
    }
}

