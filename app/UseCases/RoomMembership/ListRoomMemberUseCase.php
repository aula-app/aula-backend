<?php

declare(strict_types=1);

namespace App\UseCases\RoomMembership;

use App\Data\RoomMembership\DomainRoomMemberData;
use App\Enums\Gates;
use App\Models\LegacyRoom;
use Spatie\LaravelData\DataCollection;
use Illuminate\Support\Facades\Gate;

class ListRoomMemberUseCase
{
    /**
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @return DataCollection<array-key, DomainRoomMemberData>
     */
    public static function execute(string $roomPublicId): DataCollection
    {
        Gate::authorize(Gates::ListRoomMember);

        $legacyRoom = LegacyRoom::with('users:hash_id')
            ->where('hash_id', $roomPublicId)->firstOrFail();
        $roomUsers = $legacyRoom->users->map(fn ($user) => new DomainRoomMemberData(
            $roomPublicId,
            $user->hash_id,
            $user->pivot->room_user_level
        ));
        return DomainRoomMemberData::collect($roomUsers, DataCollection::class);
    }
}


