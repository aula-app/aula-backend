<?php

declare(strict_types=1);

namespace App\UseCases\Room;

use App\Data\Room\DomainRoomData;
use App\Models\LegacyRoom;
use Spatie\LaravelData\DataCollection;

class ListRoomsUseCase
{
    /**
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @return DataCollection
     */
    public static function execute(): DataCollection
    {
        $all = LegacyRoom::all();
        return DomainRoomData::collect($all, DataCollection::class);
    }
}

