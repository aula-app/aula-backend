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
            DB::table('au_rel_rooms_users')->where('user_id', $legacyUser->id)->delete();
            $legacyUser->deleteOrFail();
        });

        // TODO functionality from legacy model User->deleteUser, including but not limited to:
        // - remove user's delegations
        //   - if delegation system is up for a rewrite,
        //     there's nothing to do here
        // - "delete_mode==1", delete this user's...
        //   - ideas
        //   - comments
        //   - messages
        //   - group relations
        //   - likes associations
        //   - votes associations
        //   - BUT delete_mode is never actually invoked,
        //     neither from FE nor from BE?
        // - what to do about the "system event log"?
    }

}
