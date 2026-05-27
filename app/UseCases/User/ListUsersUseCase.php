<?php

namespace App\UseCases\User;

use App\Models\LegacyUser;
use Illuminate\Database\Eloquent\Collection;

class ListUsersUseCase
{
    public static function execute(): Collection
    {
        return LegacyUser::all();
    }
}
