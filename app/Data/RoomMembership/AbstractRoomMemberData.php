<?php

declare(strict_types=1);

namespace App\Data\RoomMembership;

use App\Enums\RoomUserLevel;
use Spatie\LaravelData\Data;

abstract class AbstractRoomMemberData extends Data
{
    abstract public null|string $roomPublicId { get; }
    abstract public null|string $userPublicId { get; }
    abstract public null|RoomUserLevel $roomUserLevel { get; }

    public function __construct(
        null|string $roomPublicId,
        null|string $userPublicId,
        null|RoomUserLevel $roomUserLevel,
    ) {
        $this->roomPublicId = $roomPublicId;
        $this->userPublicId = $userPublicId;
        $this->roomUserLevel = $roomUserLevel;
    }
}
