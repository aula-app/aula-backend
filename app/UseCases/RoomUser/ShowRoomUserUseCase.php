<?php

declare(strict_types=1);

namespace App\UseCases\RoomUser;

use App\Data\RoomUser\DomainRoomUserData;
use App\Enums\Gates;
use App\Models\LegacyRoom;
use App\Models\LegacyUser;
use Spatie\LaravelData\DataCollection;
use Illuminate\Support\Facades\Gate;

class ShowRoomUserUseCase
{
    public static function execute(string $roomPublicId, string $userPublicId): DomainRoomUserData
    {
        Gate::authorize(Gates::ShowRoomUser);

        $legacyRoom = LegacyRoom::with('users:hash_id')
            ->where('hash_id', $roomPublicId)
            ->withWhereHas('users', fn($q) => $q->where('hash_id', $userPublicId))
            ->firstOrFail();
        return new DomainRoomUserData(
            $legacyRoom->hash_id,
            $legacyRoom->users[0]->hash_id,
            $legacyRoom->users[0]->pivot->room_user_level,
        );
    }
}
