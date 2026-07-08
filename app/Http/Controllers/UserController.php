<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

use App\Data\User\DomainUserData;
use App\Data\User\Requests\StoreUserData;
use App\Data\User\Requests\UpdateUserData;
use App\UseCases\User\CreateUserUseCase;
use App\UseCases\User\DeleteUserUseCase;
use App\UseCases\User\ListUsersUseCase;
use App\UseCases\User\ShowUserUseCase;
use App\UseCases\User\UpdateUserUseCase;
use Spatie\LaravelData\DataCollection;

class UserController extends Controller
{
    public function __construct(
        protected CreateUserUseCase $createUserUseCase,
        protected ShowUserUseCase $showUserUseCase,
        protected ListUsersUseCase $listUsersUseCase,
        protected UpdateUserUseCase $updateUserUseCase,
        protected DeleteUserUseCase $deleteUserUseCase,
    ) {
    }

    // note: can't use #[Authorize], needs Laravel>=13
    public function index(Request $request): DataCollection
    {
        Gate::authorize('index-users');
        // Gate::authorize('admin');
        // TODO: implement
        // - pagination
        // - sorting
        // - filter by status, userlevel, room_id?
        return $this->listUsersUseCase->execute();
    }

    // TODO? public_id is nullable in DB
    public function show(string $publicId): DomainUserData
    {
        // authz in UseCase
        return $this->showUserUseCase->execute($publicId);
    }

    public function store(StoreUserData $userStoreData): DomainUserData
    {
        // Gate::authorize('admin');
        Gate::authorize('store-users');
        return $this->createUserUseCase->execute($userStoreData);
    }

    public function update(string $publicId, UpdateUserData $userUpdateData): DomainUserData
    {
        // authz in usecase
        return $this->updateUserUseCase->execute($publicId, $userUpdateData);
    }

    public function destroy(string $publicId): void
    {
        Gate::authorize('admin');
        // Gate::authorize('destroy-users');
        $this->deleteUserUseCase->execute($publicId);
    }
}
