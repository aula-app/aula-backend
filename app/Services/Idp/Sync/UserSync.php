<?php

declare(strict_types=1);

namespace App\Services\Idp\Sync;

use App\Enums\UserStatus;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\Idp\Dto\IdpEvent;
use App\Services\Idp\IdpProviders;
use App\Services\Idp\RoomEnrolment;
use App\Services\Idp\SchoolImport;
use Illuminate\Support\Facades\Log;

/**
 * Applies a user event to the tenant database.
 *
 * SchoolImport brings a school in and UserSync keeps one account in step
 * afterwards, delegating the row itself to SchoolImport::importUser() so both
 * paths write the same result, room membership and per-room roles included.
 *
 * Requires initialised tenancy.
 *
 * Converges on the state read back from the directory instead of applying the
 * reported delta, so redelivered and out-of-order events are harmless.
 *
 * A person gone upstream is archived, not deleted: the legacy ideas, votes
 * and comments tables reference `au_users_basedata` rows.
 */
final class UserSync
{
    public function __construct(
        private readonly IdpProviders $providers,
        private readonly SchoolImport $import,
        private readonly RoomEnrolment $rooms,
    ) {}

    public function handle(IdpEvent $event, Tenant $tenant, string $provider): SyncOutcome
    {
        // The action is not trusted: deliveries can arrive out of order, so
        // the read-back decides. Gone upstream archives, present converges.
        $person = $this->providers->directory($provider)->user($event->entityId);

        if ($person === null) {
            return $this->archive($event->entityId);
        }

        $this->import->importUser($tenant, $provider, $person);

        // Eduplaces reports membership changes as person events and sends no
        // group events, so the person's groups and the school's group list
        // are re-read in follow-up jobs.
        return SyncOutcome::processed(groupsToRefresh: $person->groupIds(), reconcileSchool: true);
    }

    private function archive(string $userId): SyncOutcome
    {
        $user = LegacyUser::where('idp_user_id', $userId)->first();

        if ($user === null) {
            return SyncOutcome::skipped('user_not_found_upstream');
        }

        $user->status = UserStatus::Archived;
        $user->save();

        // Leave every directory-sourced room and clear the matching `roles`
        // entries. Rooms created inside aula are left alone.
        $this->rooms->syncUserRooms($user->id, [], 0);

        Log::info('IdP: archived a user gone upstream', [
            'user_id' => $user->id,
            'idp_user_id' => $userId,
        ]);

        return SyncOutcome::processed();
    }
}
