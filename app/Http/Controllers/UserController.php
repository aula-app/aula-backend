<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\UseCases\User\CreateUserUseCase;
use App\UseCases\User\DeleteUserUseCase;
use App\UseCases\User\ListUsersUseCase;
use App\DTO\UserDTO;
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

    public function show(int $id)
    {
        return new UserResource(ShowUserUseCase::execute($id));
    }

    public function store(StoreUserRequest $storeUserRequest)
    {
        // Controller: FormRequest -> UserDTO
        // Retrieve the validated input data...
        $validated = $storeUserRequest->validated();

        // Use validated() instead of input(...) bc there might be some sanitization
        $storeUserRequest->all();
        /* $storeUserRequest; */
        $userDTO = new UserDTO(
            $storeUserRequest->validated('displayname'),
            $storeUserRequest->validated('username'),
            $storeUserRequest->validated('realname'),
            $storeUserRequest->validated('email'),
            $storeUserRequest->validated('userlevel'),
            $storeUserRequest->validated('about_me')
        );

        // UseCases - work only with DTOs
        //??? rename DTO -> DomainModel??
        $user = CreateUserUseCase::execute($userDTO);

        // Controller: UserDTO -> UserResource
        return new UserResource($user);
    }

    public function update(string $id, UpdateUserRequest $updateUserRequest)
    {
        $userDTO = new UserDTO(
            $updateUserRequest->input('displayname'),
            $updateUserRequest->input('username'),
            $updateUserRequest->input('realname'),
            $updateUserRequest->input('email'),
            $updateUserRequest->input('userlevel'),
            $updateUserRequest->input('about_me')
        );

        $user = UpdateUserUseCase::execute($id, $userDTO);

        return new UserResource($user);
    }

    public function destroy(string $id): void
    {
        DeleteUserUseCase::execute($id);
    }
}
