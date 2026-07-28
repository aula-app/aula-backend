<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Data\User\DomainUserData;
use App\Models\LegacyUser;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\DataCollection;

class ListUsersUseCase
{
    /**
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @return DataCollection<array-key, DomainUserData>
     */
    public function execute(): DataCollection
    {
        Gate::authorize('index-users');

        $all = LegacyUser::with('rooms:hash_id')->get();
        return DomainUserData::collect($all, DataCollection::class);
    }
}
