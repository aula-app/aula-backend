<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

    // note: can't use #[Authorize], needs Laravel>=13
    public function index(Request $request): DataCollection
    {
        Gate::authorize('admin');
        return $this->listRoomsUseCase->execute();
    }

    // TODO? public_id is nullable in DB
    public function show(string $publicId): DomainRoomData
    {
        Gate::authorize('admin');
        // TODO authz in UseCase
        return $this->showRoomUseCase->execute($publicId);
    }

    public function store(StoreRoomData $userStoreData): DomainRoomData
    {
        Gate::authorize('admin');
        return $this->createRoomUseCase->execute($userStoreData);
    }

    public function update(string $publicId, UpdateRoomData $userUpdateData): DomainRoomData
    {
        Gate::authorize('admin');
        // TODO authz in usecase
        return $this->updateRoomUseCase->execute($publicId, $userUpdateData);
    }

    public function destroy(string $publicId): void
    {
        Gate::authorize('admin');
        $this->deleteRoomUseCase->execute($publicId);
    }
}

