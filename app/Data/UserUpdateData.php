<?php

namespace App\Data;

use App\Data\UserData;

class UserUpdateData extends UserData
{
    public static function rules($context = null): array
    {
        return array_map(
            fn (array $ruleset) => [...$ruleset, ...['required']],
            parent::rules()
        );
    }
}
