<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\UseCases\User\CreateUserUseCase;
use App\UseCases\User\DeleteUserUseCase;
use App\UseCases\User\ListUsersUseCase;
use App\Domain\Models\User;
use App\UseCases\User\ShowUserUseCase;
use App\UseCases\User\UpdateUserUseCase;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        // TODO: implement
        // - pagination
        // - sorting
        // - filter by status, userlevel, room_id?
        return UserResource::collection(ListUsersUseCase::execute());
    }

    public function show(string $id): UserResource
    {
        return new UserResource(ShowUserUseCase::execute($id));
    }

    public function store(StoreUserRequest $storeUserRequest): UserResource
    {
        $user = User::fromRequest($storeUserRequest);
        $user = CreateUserUseCase::execute($user);
        return new UserResource($user);
    }

    public function update(string $id, UpdateUserRequest $updateUserRequest): UserResource
    {
        $user = User::fromRequest($updateUserRequest);
        $user = UpdateUserUseCase::execute($id, $user);
        return new UserResource($user);
    }

    public function destroy(string $id): void
    {
        DeleteUserUseCase::execute($id);
    }
}
