<?php

namespace App\Data;

use App\Data\UserData;

class UserStoreData extends UserData
{
    public static function rules($context = null): array
    {
        return array_map(
            fn (array $ruleset) => [...$ruleset, ...['sometimes']],
            parent::rules()
        );
    }
}
