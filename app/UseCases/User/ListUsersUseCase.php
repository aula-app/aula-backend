<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Data\User\DomainUserData;
use App\Models\LegacyUser;
use Spatie\LaravelData\DataCollection;

class ListUsersUseCase
{
    /**
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @return DataCollection
     */
    public static function execute(): DataCollection
    {
        $all = LegacyUser::all();
        return DomainUserData::collect($all, DataCollection::class);
    }
}
