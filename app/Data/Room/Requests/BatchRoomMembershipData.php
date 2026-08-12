<?php

declare(strict_types=1);

namespace App\Data\Room\Requests;

use Spatie\LaravelData\Attributes\Validation\Prohibits;
use Spatie\LaravelData\Data;
use App\Data\Room\Requests\RoomMembershipData;

class BatchRoomMembershipData extends Data
{
    // TODO: validate actual ids, not strings?
    // N.B. that requests like {"add":[]} and {"add":[],"remove"[]} are valid
    //   because [] are considered empty(ish)
    /**
     * @param null|array<int, RoomMembershipData> $add
     * @param null|array<int, string> $remove
     * @param null|array<int, RoomMembershipData> $replace
     */
    public function __construct(
        #[Prohibits(['remove', 'replace'])]
        public readonly null|array $add,

        #[Prohibits(['add', 'replace'])]
        public readonly null|array $remove,

        #[Prohibits(['add', 'remove'])]
        public readonly null|array $replace,
    ) {
    }
}
