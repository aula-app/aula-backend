<?php

declare(strict_types=1);

namespace App\UseCases\Room;

use App\Enums\Gates;
use App\Models\LegacyRoom;
use Illuminate\Support\Facades\Gate;

class DeleteRoomUseCase
{
    public function execute(string $publicId): void
    {
        Gate::authorize(Gates::DeleteRoom);

        LegacyRoom::where('hash_id', $publicId)->firstOrFail()->deleteOrFail();
    }
}
