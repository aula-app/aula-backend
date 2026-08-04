<?php

declare(strict_types=1);

namespace App\UseCases\RoomUser;

use App\Data\RoomUser\DomainRoomUserData;
use App\Enums\Gates;
use App\Models\LegacyRoom;
use Spatie\LaravelData\DataCollection;
use Illuminate\Support\Facades\Gate;

class ListRoomUserUseCase
{
    /**
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @return DataCollection<array-key, DomainRoomUserData>
     */
    public static function execute(string $roomPublicId): DataCollection
    {
        Gate::authorize(Gates::ListRoomUser);

        $legacyRoom = LegacyRoom::with('users:hash_id')
            ->where('hash_id', $roomPublicId)->firstOrFail();
        $roomUsers = $legacyRoom->users->map(fn ($user) => new DomainRoomUserData(
            $roomPublicId,
            $user->hash_id,
            $user->pivot->room_user_level
        ));
        return DomainRoomUserData::collect($roomUsers, DataCollection::class);
    }
}


