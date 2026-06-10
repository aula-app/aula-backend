<?php

namespace App\UseCases\User;

use App\Data\UserData;
use App\Models\LegacyUser;
use Spatie\LaravelData\DataCollection;

class ListUsersUseCase
{
    /**
     * Summary of execute
     * @return DataCollection<int, UserData>
     */
    public static function execute(): DataCollection
    {
        $all = LegacyUser::all();
        return UserData::collect($all, DataCollection::class);
    }
}
