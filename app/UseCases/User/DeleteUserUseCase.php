<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Models\LegacyUser;
use Illuminate\Support\Facades\Gate;

class DeleteUserUseCase
{
    public function execute(string $publicId): void
    {
        Gate::authorize('destroy-users', $publicId);

        LegacyUser::where('hash_id', $publicId)->firstOrFail()->deleteOrFail();

        // TODO functionality from legacy model User->deleteUser, including but not limited to:
        // - remove user's delegations
        // - "delete_mode==1", delete this user's...
        //   - ideas
        //   - comments
        //   - messages
        //   - group relations
        //   - room relations
        //   - likes associations
        //   - votes associations
    }

}
