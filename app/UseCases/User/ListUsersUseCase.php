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
     * @return DataCollection<array-key, DomainUserData>
     */
    public static function execute(): DataCollection
    {
        $all = LegacyUser::with('rooms:hash_id')->get();
        return DomainUserData::collect($all, DataCollection::class);
    }
}
