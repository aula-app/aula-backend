<?php

declare(strict_types=1);

namespace App\UseCases\Room;

use App\Data\Room\DomainRoomData;
use App\Models\LegacyRoom;
use Illuminate\Support\Facades\Gate;

class ShowRoomUseCase
{
    public function execute(string $publicId): DomainRoomData
    {
        // TODO
        Gate::authorize('admin');
        $legacyRoom = LegacyRoom::where('hash_id', $publicId)->firstOrFail();
        return DomainRoomData::from($legacyRoom);
    }
}

