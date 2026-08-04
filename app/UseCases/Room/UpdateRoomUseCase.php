<?php

declare(strict_types=1);

namespace App\UseCases\Room;

use App\Data\Room\Requests\UpdateRoomData;
use App\Data\Room\DomainRoomData;
use App\Enums\Gates;
use App\Models\LegacyRoom;
use Illuminate\Support\Facades\Gate;

class UpdateRoomUseCase
{
    public function execute(string $hashId, UpdateRoomData $userUpdateData): DomainRoomData
    {
        Gate::authorize(Gates::UpdateRoom);

        $legacyRoom = LegacyRoom::where('hash_id', $hashId)->firstOrFail();

        $legacyRoom->room_name = $userUpdateData->name;
        $legacyRoom->status = $userUpdateData->status;
        $legacyRoom->description_public = $userUpdateData->descriptionPublic;
        $legacyRoom->description_internal = $userUpdateData->descriptionInternal;
        $legacyRoom->phase_duration_1 = $userUpdateData->phaseDuration1;
        $legacyRoom->phase_duration_3 = $userUpdateData->phaseDuration3;

        $legacyRoom->save();
        $legacyRoom->refresh();

        return DomainRoomData::from($legacyRoom);
    }
}
