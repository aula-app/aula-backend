<?php

declare(strict_types=1);

namespace App\UseCases\Room;

use App\Models\LegacyRoom;
use App\Models\LegacyUser;
use App\Data\Room\DomainRoomData;
use App\Data\Room\Requests\BatchRoomMembershipData;
use App\Enums\Gates;
use Illuminate\Support\Facades\Gate;

class PatchRoomMembershipUseCase
{
    public function execute(string $roomPublicId, BatchRoomMembershipData $batchRoomMembershipData): DomainRoomData
    {
        Gate::authorize(Gates::PatchRoomMembership);

        $legacyRoom = LegacyRoom::where('hash_id', $roomPublicId)->sole();

        $affectedLegacyUsers = [];

        if ($batchRoomMembershipData->add !== null || $batchRoomMembershipData->replace !== null) {

            $idsAndPivots = [];

            foreach (
                $batchRoomMembershipData->add ?? $batchRoomMembershipData->replace
                as $roomMemberShipData
            ) {
                $legacyUser = LegacyUser::where('hash_id', $roomMemberShipData->publicId)->sole(['id']);
                $affectedLegacyUsers[] = $legacyUser;
                $idsAndPivots[$legacyUser->id] = $roomMemberShipData->toArray();
            }

            if ($batchRoomMembershipData->add !== null) {
                // attach would fail when a relation already exists
                $legacyRoom->users()->syncWithoutDetachingOrFail($idsAndPivots);
            } else { // replace
                $legacyRoom->users()->syncOrFail($idsAndPivots);
            }

        } elseif ($batchRoomMembershipData->remove !== null) {

            $detachIds = [];

            foreach ($batchRoomMembershipData->remove as $memberPublicId) {
                $legacyUser = LegacyUser::where('hash_id', $memberPublicId)->sole(['id']);
                $affectedLegacyUsers[] = $legacyUser;
                $detachIds[] = $legacyUser->id;
            }

            $legacyRoom->users()->detachOrFail($detachIds);

        } else {
            abort(422, 'request needs add, remove or replace');
        }

        foreach ($affectedLegacyUsers as $legacyUser) {
            $legacyUser->updateRolesJson();
            $legacyUser->saveOrFail();
        }

        return DomainRoomData::from($legacyRoom);
    }
}
