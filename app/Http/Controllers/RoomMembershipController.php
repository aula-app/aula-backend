<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\RoomMembership\Requests\StoreRoomMemberData;
use App\Data\RoomMembership\DomainRoomMemberData;
use App\UseCases\RoomMembership\CreateRoomMemberUseCase;
use App\UseCases\RoomMembership\DeleteRoomMemberUseCase;
use App\UseCases\RoomMembership\ListRoomMemberUseCase;
use App\UseCases\RoomMembership\ShowRoomMemberUseCase;
use Spatie\LaravelData\DataCollection;
use Illuminate\Support\Facades\Gate;

// TODO Authz

class RoomMembershipController extends Controller
{
    public function __construct(
        protected CreateRoomMemberUseCase $createRoomMemberUseCase,
        protected ListRoomMemberUseCase $listRoomMemberUseCase,
        protected ShowRoomMemberUseCase $showRoomMemberUseCase,
        protected DeleteRoomMemberUseCase $deleteRoomMemberUseCase,
    ) {
    }

    /**
     * @return DataCollection<array-key, DomainRoomMemberData>
     */
    public function index(string $room): DataCollection
    {
        return $this->listRoomMemberUseCase->execute($room);
    }

    public function show(string $room, string $user): DomainRoomMemberData
    {
        return $this->showRoomMemberUseCase->execute($room, $user);
    }

    public function store(string $room, string $user, StoreRoomMemberData $storeRoomMemberData): DomainRoomMemberData
    {
        return $this->createRoomMemberUseCase->execute($room, $user, $storeRoomMemberData);
    }

    public function destroy(string $room, string $user): void
    {
        $this->deleteRoomMemberUseCase->execute($room, $user);
    }
}
