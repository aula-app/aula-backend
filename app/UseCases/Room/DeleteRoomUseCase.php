<?php

declare(strict_types=1);

namespace App\UseCases\Room;

use App\Models\LegacyRoom;

class DeleteRoomUseCase
{
    public function execute(string $publicId): void
    {
        LegacyRoom::where('hash_id', $publicId)->firstOrFail()->deleteOrFail();
    }
}
