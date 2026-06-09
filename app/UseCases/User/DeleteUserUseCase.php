<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Models\LegacyUser;

class DeleteUserUseCase
{
    public function execute(string $id): void
    {
        LegacyUser::findOrFail($id);
        LegacyUser::destroy($id);
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
