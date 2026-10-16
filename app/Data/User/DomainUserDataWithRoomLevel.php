<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Enums\RoomUserLevel;

class DomainUserDataWithRoomLevel extends DomainUserData
{
    public null|RoomUserLevel $roomLevel {
        get => $this->pivot->room_user_level ?? null;
    }
}
