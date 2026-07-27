<?php

declare(strict_types=1);

namespace App\Data\RoomUser\Requests;

use App\Data\RoomUser\AbstractRoomUserData;
use App\Enums\RoomUserLevel;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Attributes\Hidden;

class StoreRoomUserData extends AbstractRoomUserData
{
    #[Rule('missing')]
    #[Hidden]
    public readonly null|string $roomPublicId;

    #[Rule('missing')]
    #[Hidden]
    public readonly null|string $userPublicId;

    #[MapName('room_user_level')]
    public readonly RoomUserLevel $roomUserLevel;
}
