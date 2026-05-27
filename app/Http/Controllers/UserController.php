<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\LegacyUser;
use App\UseCases\User\CreateUserUseCase;
use App\UseCases\User\DeleteUserUseCase;
use App\UseCases\User\ListUsersUseCase;
use App\DTO\UserDTO;
use App\UseCases\User\UpdateUserUseCase;

class UserController extends Controller {

    public function index()
    {
        // TODO: implement
        // - pagination
        // - sorting
        // - filter by status, userlevel, room_id?
        return UserResource::collection(ListUsersUseCase::execute());
    }

    public function show(int $id) {
        return LegacyUser::findOrFail($id);
    }

    public function store(StoreUserRequest $storeUserRequest)
    {
        $userDTO = new UserDTO(
            $storeUserRequest->input('displayname'),
            $storeUserRequest->input('username'),
            $storeUserRequest->input('realname'),
            $storeUserRequest->input('email'),
            $storeUserRequest->input('userlevel'),
            $storeUserRequest->input('about_me')
        );

        $user = CreateUserUseCase::execute($userDTO);

        return new UserResource($user);
    }

    public function update(string $id, UpdateUserRequest $updateUserRequest)
    {
        $legacyUser = LegacyUser::findOrFail($id);

        $userDTO = new UserDTO(
            $updateUserRequest->input('displayname'),
            $updateUserRequest->input('username'),
            $updateUserRequest->input('realname'),
            $updateUserRequest->input('email'),
            $updateUserRequest->input('userlevel'),
            $updateUserRequest->input('about_me')
        );

        $user = UpdateUserUseCase::execute($legacyUser, $userDTO);

        return new UserResource($user);
    }

    public function destroy(string $id): void
    {
        $legacyUser = LegacyUser::findOrFail($id);
        DeleteUserUseCase::execute($legacyUser);
    }
}
