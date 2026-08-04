<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Data\Room\DomainRoomData;
use App\Data\Room\Requests\StoreRoomData;
use App\Data\Room\Requests\UpdateRoomData;
use App\UseCases\Room\CreateRoomUseCase;
use App\UseCases\Room\DeleteRoomUseCase;
use App\UseCases\Room\ListRoomsUseCase;
use App\UseCases\Room\ShowRoomUseCase;
use App\UseCases\Room\UpdateRoomUseCase;
use Spatie\LaravelData\DataCollection;

class RoomController extends Controller
{
    public function __construct(
        protected CreateRoomUseCase $createRoomUseCase,
        protected ShowRoomUseCase $showRoomUseCase,
        protected ListRoomsUseCase $listRoomsUseCase,
        protected UpdateRoomUseCase $updateRoomUseCase,
        protected DeleteRoomUseCase $deleteRoomUseCase,
    ) {
    }

    public function index(Request $request): DataCollection
    {
        return $this->listRoomsUseCase->execute();
    }

    public function show(string $publicId): DomainRoomData
    {
        return $this->showRoomUseCase->execute($publicId);
    }

    public function store(StoreRoomData $userStoreData): DomainRoomData
    {
        return $this->createRoomUseCase->execute($userStoreData);
    }

    public function update(string $publicId, UpdateRoomData $userUpdateData): DomainRoomData
    {
        return $this->updateRoomUseCase->execute($publicId, $userUpdateData);
    }

    public function destroy(string $publicId): void
    {
        $this->deleteRoomUseCase->execute($publicId);
    }
}

