<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Data\User\DomainUserData;
use App\Models\LegacyUser;

class ShowUserUseCase
{
    public function execute(string $publicId): DomainUserData
    {
        $legacyUser = LegacyUser::where('hash_id', $publicId)->firstOrFail();
        return DomainUserData::from($legacyUser);
    }
}
