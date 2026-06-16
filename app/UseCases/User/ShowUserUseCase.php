<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Data\UserModelData;
use App\Models\LegacyUser;

class ShowUserUseCase
{
    public function execute(string $publicId): UserModelData
    {
        $legacyUser = LegacyUser::where('hash_id', $publicId)->firstOrFail();
        return UserModelData::from($legacyUser);
    }
}
