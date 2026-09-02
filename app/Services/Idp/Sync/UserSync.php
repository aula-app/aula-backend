<?php

declare(strict_types=1);

namespace App\Services\Idp\Sync;

use App\Enums\UserStatus;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\Idp\Dto\IdpEvent;
use App\Services\Idp\Dto\IdpUser;
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
 * ACTION_DELETE archives instead of deleting: the legacy ideas, votes and
 * comments tables reference `au_users_basedata` rows.
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
        if ($event->action === IdpEvent::ACTION_DELETE) {
            return $this->archive($event->entityId);
        }

        $person = $this->fetch($provider, $event->entityId);

        if ($person === null) {
            // Deleted between the event firing and this read-back. Not an
            // error, and not a reason to drop a local row: an ACTION_DELETE
            // event says so explicitly.
            return SyncOutcome::skipped('user_not_found_upstream');
        }

        $user = $this->import->importUser($tenant, $provider, $person);

        if ($event->action === IdpEvent::ACTION_RESTORE && ! $user->isActive()) {
            // ACTION_RESTORE means active even when the directory read-back
            // still reports otherwise.
            $user->status = UserStatus::Active;
            $user->save();
        }

        return SyncOutcome::processed();
    }

    /**
     * Read the user back from the directory.
     *
     * A provider can offer a richer single-entity lookup than IdentityDirectory
     * requires, such as EduplacesDirectory::personOrUser(), which merges two
     * upstream views. IdentityDirectory::user() is the fallback.
     */
    private function fetch(string $provider, string $userId): ?IdpUser
    {
        $directory = $this->providers->directory($provider);

        return method_exists($directory, 'personOrUser')
            ? $directory->personOrUser($userId)
            : $directory->user($userId);
    }

    private function archive(string $userId): SyncOutcome
    {
        $user = LegacyUser::where('idp_user_id', $userId)->first();

        if ($user === null) {
            return SyncOutcome::skipped('user_not_local');
        }

        $user->status = UserStatus::Archived;
        $user->save();

        // Leave every directory-sourced room and clear the matching `roles`
        // entries. Rooms created inside aula are left alone.
        $this->rooms->syncUserRooms($user->id, [], 0);

        Log::info('IdP: archived a user on directory delete', [
            'user_id' => $user->id,
            'idp_user_id' => $userId,
        ]);

        return SyncOutcome::processed();
    }
}
