<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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

    public function index(): DataCollection
    {
        // TODO: implement
        // - pagination
        // - sorting
        // - filter by status, userlevel, room_id?
        return $this->listUsersUseCase->execute();
    }

    // TODO? public_id is nullable in DB
    public function show(string $publicId): DomainUserData
    {
        return $this->showUserUseCase->execute($publicId);
    }

    public function store(StoreUserData $userStoreData): DomainUserData
    {
        return $this->createUserUseCase->execute($userStoreData);
    }

    public function update(string $publicId, UpdateUserData $userUpdateData): DomainUserData
    {
        return $this->updateUserUseCase->execute($publicId, $userUpdateData);
    }

    public function destroy(string $publicId): void
    {
        $this->deleteUserUseCase->execute($publicId);
    }
}
