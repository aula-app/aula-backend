<?php

declare(strict_types=1);

namespace App\Data\RoomUser;

use App\Enums\RoomUserLevel;
use Spatie\LaravelData\Attributes\MapOutputName;

class DomainRoomUserData extends AbstractRoomUserData
{
    public readonly string $roomPublicId;

    public readonly string $userPublicId;

    public readonly RoomUserLevel $roomUserLevel;

}
