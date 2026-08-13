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
 * The ongoing-drift counterpart to SchoolImport: the import brings a school in,
 * this keeps one person in step afterwards. Creating and converging the row is
 * delegated to the import, so both paths produce identical results — room
 * membership and per-room roles included.
 *
 * Must be called with tenancy already initialised.
 *
 * Converges on the state read back from the directory rather than applying the
 * reported delta, so redelivered and out-of-order events are harmless.
 *
 * Deletes archive rather than remove: legacy tables (ideas, votes, comments)
 * reference user rows, and someone leaving a school should not take that
 * history with them.
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
            // Gone between the event firing and us handling it. Not an error,
            // and not a reason to destroy a row we hold — a later delete event
            // will say so explicitly.
            return SyncOutcome::skipped('user_not_found_upstream');
        }

        $user = $this->import->importUser($tenant, $provider, $person);

        if ($event->action === IdpEvent::ACTION_RESTORE && ! $user->isActive()) {
            // A restore says active even if the directory has not caught up.
            $user->status = UserStatus::Active;
            $user->save();
        }

        return SyncOutcome::processed();
    }

    /**
     * Read the user back from the directory.
     *
     * A provider may offer a richer single-entity lookup than the contract
     * requires — some merge several upstream views — so use that when
     * it exists and the plain contract method otherwise.
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

        // Drop them out of the synced rooms and clear the matching entries in
        // `roles`; rooms created inside aula are left alone.
        $this->rooms->syncUserRooms($user->id, [], 0);

        Log::info('IdP: archived a user on directory delete', [
            'user_id' => $user->id,
            'idp_user_id' => $userId,
        ]);

        return SyncOutcome::processed();
    }
}
