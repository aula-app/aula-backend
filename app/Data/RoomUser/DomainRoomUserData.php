<?php

declare(strict_types=1);

namespace App\Data\RoomUser;

use App\Enums\RoomUserLevel;
use Spatie\LaravelData\Attributes\MapOutputName;

class DomainRoomUserData extends AbstractRoomUserData
{
    #[MapOutputName('room_public_id')]
    public readonly string $roomPublicId;

    #[MapOutputName('user_public_id')]
    public readonly string $userPublicId;

    #[MapOutputName('room_user_level')]
    public readonly RoomUserLevel $roomUserLevel;

}
