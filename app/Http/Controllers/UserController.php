<?php

namespace App\Http\Controllers;

use App\UseCases\User\CreateUserUseCase;
use App\UseCases\User\DeleteUserUseCase;
use App\UseCases\User\ListUsersUseCase;
use App\UseCases\User\ShowUserUseCase;
use App\UseCases\User\UpdateUserUseCase;
use Spatie\LaravelData\DataCollection;

use App\Data\UserData;
use App\Data\UserStoreData;
use App\Data\UserUpdateData;

class UserController extends Controller
{
    public function index(): DataCollection
    {
        // TODO: implement
        // - pagination
        // - sorting
        // - filter by status, userlevel, room_id?
        return ListUsersUseCase::execute();
    }

    // TODO? hash_id is nullable in DB
    public function show(string $hashId): UserData
    {
        return (new ShowUserUseCase())->execute($hashId);
    }

    public function store(UserStoreData $userStoreData): UserData
    {
        // $user = User::fromRequest($storeUserRequest);
        return (new CreateUserUseCase())->execute($userStoreData);
    }

    public function update(string $hashId, UserUpdateData $userUpdateData): UserData
    {
        return (new UpdateUserUseCase())->execute($hashId, $userUpdateData);
    }

    public function destroy(string $hashId): void
    {
        (new DeleteUserUseCase())->execute($hashId);
    }
}
