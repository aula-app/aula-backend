<?php

declare(strict_types=1);

namespace App\UseCases\Room;

use App\Data\Room\DomainRoomData;
use App\Data\Room\Requests\StoreRoomData;
use App\Models\LegacyRoom;
use Illuminate\Support\Str;

class CreateRoomUseCase
{
    public function execute(StoreRoomData $storeRoomData): DomainRoomData
    {
        Gate::authorize(Gates::CreateRoom);

        // TODO these need defaults (or need to be required)
        $legacyRoom = new LegacyRoom();
        $legacyRoom->hash_id = Str::random(32);
        $legacyRoom->room_name = $storeRoomData->name;
        $legacyRoom->status = $storeRoomData->status;
        $legacyRoom->description_public = $storeRoomData->descriptionPublic;
        $legacyRoom->description_internal = $storeRoomData->descriptionInternal;
        $legacyRoom->phase_duration_1 = $storeRoomData->phaseDuration1;
        $legacyRoom->phase_duration_3 = $storeRoomData->phaseDuration3;
        $legacyRoom->save();
        // let createdAt update
        $legacyRoom->refresh();
        return DomainRoomData::from($legacyRoom);
    }
}
