<?php

declare(strict_types=1);

namespace App\Data\Room\Requests;

use Spatie\LaravelData\Data;
use App\Data\Room\Requests\RoomMembershipData;

/**
 * Adds and/or removes several users (hence batch) to/from a room's members
 */
class BatchRoomMembershipData extends Data
{
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
