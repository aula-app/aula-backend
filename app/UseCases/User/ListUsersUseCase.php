<?php

namespace App\UseCases\User;

use App\Data\UserModelData;
use App\Models\LegacyUser;
use Spatie\LaravelData\DataCollection;

class ListUsersUseCase
{
    /**
     * Summary of execute
     * @return DataCollection<int, UserModelData>
     */
    public static function execute(): DataCollection
    {
        $all = LegacyUser::all();
        return UserModelData::collect($all, DataCollection::class);
    }
}
