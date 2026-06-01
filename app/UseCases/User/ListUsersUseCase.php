<?php

namespace App\UseCases\User;

use App\Domain\Models\User;
use App\Models\LegacyUser;
use Illuminate\Support\Collection;

class ListUsersUseCase
{
    public static function execute(): Collection
    {
        return LegacyUser::all()->map(fn ($legacyUser) => User::fromLegacy($legacyUser));
    }
}
