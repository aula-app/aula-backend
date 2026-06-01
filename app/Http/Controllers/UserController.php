<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\UseCases\User\CreateUserUseCase;
use App\UseCases\User\DeleteUserUseCase;
use App\UseCases\User\ListUsersUseCase;
use App\DTO\UserDTO;
use App\Domain\Models\User;
use App\UseCases\User\ShowUserUseCase;
use App\UseCases\User\UpdateUserUseCase;

class UserController extends Controller
{
    public function index()
    {
        // TODO: implement
        // - pagination
        // - sorting
        // - filter by status, userlevel, room_id?
        return UserResource::collection(ListUsersUseCase::execute());
    }

    public function show(string $id)
    {
        return new UserResource(ShowUserUseCase::execute($id));
    }

    public function store(StoreUserRequest $storeUserRequest): UserResource
    {
        // Controller: FormRequest -> UserDTO
        // Retrieve the validated input data...
        $user = User::fromRequest($storeUserRequest);

        // UseCases - work only with DTOs
        // ??? rename DTO -> DomainModel??
        $user = CreateUserUseCase::execute($user);

        // Controller: UserDTO -> UserResource
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
