<?php

declare(strict_types=1);

namespace App\UseCases\RoomMembership;

use App\Data\RoomMembership\DomainRoomMemberData;
use App\Enums\Gates;
use App\Models\LegacyRoom;
use App\Models\LegacyUser;
use Spatie\LaravelData\DataCollection;
use Illuminate\Support\Facades\Gate;

class ShowRoomMemberUseCase
{
    /**
     * @psalm-suppress UndefinedMagicPropertyFetch
     */
    public static function execute(string $roomPublicId, string $userPublicId): DomainRoomMemberData
    {
        Gate::authorize(Gates::ShowRoomMember);

        $legacyRoom = LegacyRoom::with('users:hash_id')
            ->where('hash_id', $roomPublicId)
            ->withWhereHas('users', fn($q) => $q->where('hash_id', $userPublicId))
            ->firstOrFail();
        return new DomainRoomMemberData(
            $legacyRoom->hash_id,
            $legacyRoom->users[0]->hash_id,
            $legacyRoom->users[0]->pivot->room_user_level,
        );
    }
}
