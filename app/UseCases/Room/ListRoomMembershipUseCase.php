<?php

declare(strict_types=1);

namespace App\UseCases\Room;

use App\Data\User\DomainUserData;
use App\Data\User\DomainUserDataWithRoomLevel;
use App\Models\LegacyRoom;
use App\Models\LegacyUser;
use App\Data\Room\DomainRoomData;
use App\Enums\Gates;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\DataCollection;

class ListRoomMembershipUseCase
{
    /**
     * @param string $roomPublicId
     * @return DataCollection<array-key, DomainUserDataWithRoomLevel>
     */
    public function execute(string $roomPublicId): DataCollection
    {
        Gate::authorize(Gates::ListRoomMembership);
        $legacyRoom = LegacyRoom::where('hash_id', $roomPublicId)->sole();
        return DomainUserDataWithRoomLevel::collect($legacyRoom->users, DataCollection::class);
    }
}
