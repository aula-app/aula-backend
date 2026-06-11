<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Data\UserModelData;
use App\Models\LegacyUser;

class ShowUserUseCase
{
    public function execute(string $hashId): UserModelData
    {
        $legacyUser = LegacyUser::where('hash_id', $hashId)->firstOrFail();
        return UserModelData::from($legacyUser);
    }
}
