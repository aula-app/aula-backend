<?php

declare(strict_types=1);

namespace App\UseCases\Room;

use App\Data\Room\DomainRoomData;
use App\Enums\Gates;
use App\Models\LegacyRoom;
use Illuminate\Support\Facades\Gate;

class ShowRoomUseCase
{
    public function execute(string $publicId): DomainRoomData
    {
        Gate::authorize(Gates::ShowRoom);

        $legacyRoom = LegacyRoom::where('hash_id', $publicId)->firstOrFail();
        return DomainRoomData::from($legacyRoom);
    }
}

