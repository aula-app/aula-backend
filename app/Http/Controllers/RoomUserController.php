<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\RoomUser\Requests\StoreRoomUserData;
use App\Data\RoomUser\DomainRoomUserData;
use App\UseCases\RoomUser\CreateRoomUserUseCase;
use App\UseCases\RoomUser\DeleteRoomUserUseCase;
use App\UseCases\RoomUser\ListRoomUserUseCase;
use App\UseCases\RoomUser\ShowRoomUserUseCase;
use Spatie\LaravelData\DataCollection;
use Illuminate\Support\Facades\Gate;

// TODO Authz

class RoomUserController extends Controller
{
    public function __construct(
        protected CreateRoomUserUseCase $createRoomUserUseCase,
        protected ListRoomUserUseCase $listRoomUserUseCase,
        protected ShowRoomUserUseCase $showRoomUserUseCase,
        protected DeleteRoomUserUseCase $deleteRoomUserUseCase,
    ) {
    }

    /**
     * @return DataCollection<array-key, DomainRoomUserData>
     */
    public function index(string $room): DataCollection
    {
        Gate::authorize('admin');
        return $this->listRoomUserUseCase->execute($room);
    }

    public function show(string $room, string $user): DomainRoomUserData
    {
        Gate::authorize('admin');
        return $this->showRoomUserUseCase->execute($room, $user);
    }

    public function store(string $room, string $user, StoreRoomUserData $storeRoomUserData): DomainRoomUserData
    {
        Gate::authorize('admin');
        return $this->createRoomUserUseCase->execute($room, $user, $storeRoomUserData);
    }

    public function destroy(string $room, string $user): void
    {
        Gate::authorize('admin');
        $this->deleteRoomUserUseCase->execute($room, $user);
    }
}
