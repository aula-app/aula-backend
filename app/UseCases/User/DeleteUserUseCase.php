<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Models\LegacyUser;
use App\Relations\RoomMember;
use Illuminate\Support\Facades\Gate;
use DB;
use App\Enums\Gates;

class DeleteUserUseCase
{
    public function execute(string $publicId): void
    {
        Gate::authorize(Gates::DeleteUser, $publicId);

        $legacyUser = LegacyUser::where('hash_id', $publicId)->sole(['id']);
        DB::transaction(function () use ($legacyUser) {
            // or, detach?
            // TODO add a test
            DB::table('au_rel_rooms_users')->where('user_id', $legacyUser->id)->delete();
            $legacyUser->deleteOrFail();
        });

        // TODO functionality from legacy model User->deleteUser, including but not limited to:
        // - remove user's delegations
        // - "delete_mode==1", delete this user's...
        //   - ideas
        //   - comments
        //   - messages
        //   - group relations
        //   - likes associations
        //   - votes associations
    }

}
