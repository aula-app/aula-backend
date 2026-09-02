<?php

declare(strict_types=1);

namespace App\Data\Room\Requests;

use Spatie\LaravelData\Attributes\Validation\Prohibits;
use Spatie\LaravelData\Data;
use App\Data\Room\Requests\RoomMembershipData;

class BatchRoomMembershipData extends Data
{
    // TODO: validate actual ids, not strings?
    /**
     * @param null|array<int, RoomMembershipData> $add
     * @param null|array<int, string> $remove
     */
    public function __construct(
        public readonly null|array $add,
        public readonly null|array $remove,
    ) {
    }
}
