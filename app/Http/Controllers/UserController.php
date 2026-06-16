<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\UseCases\User\CreateUserUseCase;
use App\UseCases\User\DeleteUserUseCase;
use App\UseCases\User\ListUsersUseCase;
use App\UseCases\User\ShowUserUseCase;
use App\UseCases\User\UpdateUserUseCase;
use Spatie\LaravelData\DataCollection;

use App\Data\UserModelData;
use App\Data\UserStoreData;
use App\Data\UserUpdateData;

class UserController extends Controller
{
    public function __construct(
        protected CreateUserUseCase $createUserUseCase,
        protected ShowUserUseCase $showUserUseCase,
        protected ListUsersUseCase $listUsersUseCase,
        protected UpdateUserUseCase $updateUserUseCase,
        protected DeleteUserUseCase $deleteUserUseCase,
    ) {}

    public function index(): DataCollection
    {
        // TODO: implement
        // - pagination
        // - sorting
        // - filter by status, userlevel, room_id?
        return $this->listUsersUseCase->execute();
    }

    // TODO? hash_id is nullable in DB
    public function show(string $hashId): UserModelData
    {
        return $this->showUserUseCase->execute($hashId);
    }

    public function store(UserStoreData $userStoreData): UserModelData
    {
        return $this->createUserUseCase->execute($userStoreData);
    }

    public function update(string $hashId, UserUpdateData $userUpdateData): UserModelData
    {
        return $this->updateUserUseCase->execute($hashId, $userUpdateData);
    }

    public function destroy(string $hashId): void
    {
        $this->deleteUserUseCase->execute($hashId);
    }
}
