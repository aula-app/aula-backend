<?php

declare(strict_types=1);

namespace App\Data\Room\Requests;

use App\Enums\RoomUserLevel;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Attributes\MapOutputName;

class RoomMemberShipData extends Data
{
    public function __construct(
        #[Hidden]
        public readonly string $publicId,

        #[MapOutputName('room_user_level')]
        public readonly RoomUserLevel $level,
    ) {
    }
}
