<?php

declare(strict_types=1);

namespace App\UseCases\Room;

use App\Data\Room\DomainRoomData;
use App\Enums\Gates;
use App\Models\LegacyRoom;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\DataCollection;

class ListRoomsUseCase
{
    /**
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @return DataCollection<array-key, DomainRoomData>
     */
    public static function execute(): DataCollection
    {
        Gate::authorize(Gates::ListRooms);

        $all = LegacyRoom::all();

        return DomainRoomData::collect($all, DataCollection::class);
    }
}
