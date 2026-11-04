<?php

declare(strict_types=1);

namespace App\UseCases\Room;

use App\Models\LegacyRoom;
use App\Models\LegacyUser;
use App\Data\Room\DomainRoomData;
use App\Data\Room\Requests\BatchRoomMembershipData;
use App\Enums\Gates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PatchRoomMembershipUseCase
{
    public function execute(string $roomPublicId, BatchRoomMembershipData $batchRoomMembershipData): DomainRoomData
    {
        Gate::authorize(Gates::PatchRoomMembership);

        $legacyRoom = LegacyRoom::where('hash_id', $roomPublicId)->sole();

        DB::transaction(function () use ($legacyRoom, $batchRoomMembershipData) {

            $affectedLegacyUsers = [];

            if ($batchRoomMembershipData->remove !== null) {
                $detachIds = [];

                foreach ($batchRoomMembershipData->remove as $memberPublicId) {
                    $legacyUser = LegacyUser::where('hash_id', $memberPublicId)->sole(['id']);
                    $affectedLegacyUsers[] = $legacyUser;
                    $detachIds[] = $legacyUser->id;
                }

                $legacyRoom->users()->detachOrFail($detachIds);
            }

            if ($batchRoomMembershipData->add !== null) {
                $idsAndPivots = [];

                foreach ($batchRoomMembershipData->add as $roomMemberShipData) {
                    $legacyUser = LegacyUser::where('hash_id', $roomMemberShipData->publicId)->sole(['id']);
                    $affectedLegacyUsers[] = $legacyUser;
                    $idsAndPivots[$legacyUser->id] = $roomMemberShipData->toArray();
                }

                $legacyRoom->users()->syncWithoutDetachingOrFail($idsAndPivots);
            }

            foreach ($affectedLegacyUsers as $legacyUser) {
                $legacyUser->updateRolesJson();
                $legacyUser->saveOrFail();
            }

        });

        return DomainRoomData::from($legacyRoom);
    }
}
