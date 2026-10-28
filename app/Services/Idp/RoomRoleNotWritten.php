<?php

declare(strict_types=1);

namespace App\Services\Idp;

use RuntimeException;

final class RoomRoleNotWritten extends RuntimeException
{
    public function __construct(
        public readonly int $userId,
        public readonly string $roomHashId,
    ) {
        parent::__construct("Room role not written: user {$userId} not found for room {$roomHashId}");
    }
}
