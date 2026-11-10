<?php

declare(strict_types=1);

namespace App\Data\RoomMembership\Requests;

use App\Data\RoomMembership\AbstractRoomMemberData;
use App\Enums\RoomMemberRole;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Attributes\Hidden;

class StoreRoomMemberData extends AbstractRoomMemberData
{
    #[Rule('missing')]
    #[Hidden]
    public readonly null|string $roomPublicId;

    #[Rule('missing')]
    #[Hidden]
    public readonly null|string $userPublicId;

    public readonly RoomMemberRole $roomMemberRole;
}
