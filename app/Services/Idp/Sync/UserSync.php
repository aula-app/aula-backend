<?php

declare(strict_types=1);

namespace App\Services\Idp\Sync;

use App\Enums\UserStatus;
use App\Jobs\ReconcileIdpSchool;
use App\Jobs\RefreshIdpGroup;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\Idp\IdpProviders;
use App\Services\Idp\RoomEnrolment;
use App\Services\Idp\SchoolImport;
use Illuminate\Support\Facades\Log;

use function Illuminate\Support\now;

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
    /**
     * UserSync dispatches RefreshIdpGroup and ReconcileIdpSchool. In order to reduce
     * repetition of those jobs, they are dispatched with a delay, so that any
     * duplicate dispatches within that delay are ignored.
     */
    public const int CASCADED_SYNC_DELAY_SECONDS = 10;

    public function __construct(
        private readonly IdpProviders $providers,
        private readonly SchoolImport $import,
        private readonly RoomEnrolment $rooms,
    ) {
    }

    public function handle(Tenant $tenant, string $provider, string $userId): SyncOutcome
    {
        Log::withContext(['idp_user_id' => $userId]);

        $outcome = $this->syncUser($userId, $tenant, $provider);

        if (! $outcome->wasProcessed) {
            Log::info('IdP: user sync job changed nothing', [
                'reason' => $outcome->reason,
            ]);
        }

        tenancy()->central(function () use ($outcome, $tenant, $provider) {
            foreach ($outcome->groupsToRefresh as $groupId) {
                RefreshIdpGroup::dispatch((string) $tenant->id, $provider, $groupId)
                    ->delay(now()->plus(seconds: self::CASCADED_SYNC_DELAY_SECONDS));
            }

            ReconcileIdpSchool::dispatch((string) $tenant->id, $provider)
                ->delay(now()->plus(seconds: self::CASCADED_SYNC_DELAY_SECONDS));
        });

        return $outcome;
    }

    public function syncUser(string $userId, Tenant $tenant, string $provider): SyncOutcome
    {
        // The action is not trusted: deliveries can arrive out of order, so
        // the read-back decides. Gone upstream archives, present converges.
        $person = $this->providers->directory($provider)->user($userId);

        if ($person === null) {
            return $this->archive($userId);
        }

        $user = $this->import->importUser($tenant, $provider, $person);

        // Eduplaces reports membership changes as person events and sends no
        // group events, so the person's groups and the school's group list
        // are re-read in follow-up jobs.
        return SyncOutcome::processed(groupsToRefresh: $person->groupIds());
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
