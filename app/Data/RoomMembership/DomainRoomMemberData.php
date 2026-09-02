<?php

declare(strict_types=1);

namespace App\Data\RoomMembership;

use App\Enums\RoomUserLevel;

class DomainRoomMemberData extends AbstractRoomMemberData
{
    public readonly string $roomPublicId;

    public readonly string $userPublicId;

    public readonly RoomUserLevel $roomUserLevel;

}
