<?php

declare(strict_types=1);

namespace App\Data\Room\Requests;

use App\Enums\RoomMemberRole;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Attributes\MapOutputName;

class RoomMembershipData extends Data
{
    public function __construct(
        #[Hidden]
        public readonly string $userPublicId,

        #[MapOutputName('room_member_role')]
        public readonly RoomMemberRole $roomMemberRole,
    ) {
    }
}
