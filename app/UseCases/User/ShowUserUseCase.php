<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Data\User\DomainUserData;
use App\Models\LegacyUser;
use Illuminate\Support\Facades\Gate;

class ShowUserUseCase
{
    public function execute(string $publicId): DomainUserData
    {
        // TODO: "method-based" vs "capability-based"
        Gate::authorize('show-users', [$publicId]);
        // Gate::authorize('user-self', $publicId);

        // Gate::allowIf(fn (LegacyUser $user) => $user->isAdmin() || $user->hash_id === $publicId);

        $legacyUser = LegacyUser::where('hash_id', $publicId)->firstOrFail();
        return DomainUserData::from($legacyUser);
    }
}
