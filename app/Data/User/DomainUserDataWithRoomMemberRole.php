<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Enums\RoomMemberRole;

class DomainUserDataWithRoomMemberRole extends DomainUserData
{
    public null|RoomMemberRole $roomMemberRole {
        get => $this->pivot->room_member_role ?? null;
    }
}
