<?php

declare(strict_types=1);

namespace App\Services\Idp\Sync;

use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\Idp\Dto\IdpEvent;
use App\Services\Idp\Dto\IdpGroup;
use App\Services\Idp\Dto\IdpUser;
use App\Services\Idp\IdpProviders;
use App\Services\Idp\RoleMap;
use App\Services\Idp\RoomEnrolment;
use App\Services\Idp\SchoolImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies a group event to the tenant database.
 *
 * A directory group is an aula room, so this keeps `au_rooms` and
 * `au_rel_rooms_users` in step after SchoolImport has run, through the same
 * RoomEnrolment methods the import uses.
 *
 * Requires initialised tenancy.
 *
 * Converges on the state read back from the directory instead of applying the
 * reported delta, so redelivered and out-of-order events are harmless.
 */
final class GroupSync
{
    public function __construct(
        private readonly IdpProviders $providers,
        private readonly RoomEnrolment $rooms,
        private readonly RoleMap $roles,
        private readonly SchoolImport $import,
    ) {
    }

    public function handle(IdpEvent $event, Tenant $tenant, string $provider): SyncOutcome
    {
        if ($event->action === IdpEvent::ACTION_DELETE) {
            return $this->rooms->archiveRoom($event->entityId)
                ? SyncOutcome::processed()
                : SyncOutcome::skipped('room_not_local');
        }

        $forceActive = $event->action === IdpEvent::ACTION_RESTORE;

        return $this->refreshGroup($event->entityId, $tenant, $provider, $forceActive)
            ? SyncOutcome::processed()
            : SyncOutcome::skipped('group_not_found_upstream');
    }

    /**
     * Converge one room on the group read back from the directory: name,
     * status, members and the initial per-room role of new members. Members
     * without a local row are imported.
     *
     * Used for group events and, per group of the person, for person events.
     * Returns false when the group is gone upstream.
     */
    public function refreshGroup(string $groupId, Tenant $tenant, string $provider, bool $forceActive = false): bool
    {
        $group = $this->providers->directory($provider)->group($groupId);

        if ($group === null) {
            return false;
        }

        // The room comes first, so importMember() below enrols each new member.
        $room = $this->rooms->upsertRoom($group->id, $group->name, $forceActive || $group->isActive());

        $local = LegacyUser::whereIn('idp_user_id', $group->memberIds())
            ->pluck('idp_user_id')
            ->all();

        $imported = 0;

        // Built from the member list instead of a `/people/{id}` read per
        // member; the member's own person event fills in the rest.
        foreach ($group->members as $member) {
            if (! in_array($member->id, $local, true)) {
                $this->import->importMember($tenant, $provider, $member);
                $imported++;
            }
        }

        $this->syncMembers($room, $group->members, $provider);

        if ($imported > 0) {
            Log::info('IdP: imported group members aula did not hold', [
                'room_id' => $room['id'],
                'idp_group_id' => $group->id,
                'imported' => $imported,
            ]);
        }

        return true;
    }

    /**
     * Converge the tenant's rooms on the school's group list.
     *
     * Eduplaces sends no group events, so a group added to the sync, emptied
     * or deleted upstream surfaces only here. An active room whose group is no
     * longer listed is archived, with its memberships and `roles` entries.
     *
     * Skipped when the school itself is not visible: a school disconnected
     * from the app lists no groups, which must not archive every room.
     *
     * @param  list<string>  $alreadyRefreshed  provider group ids the same event already refreshes
     * @return list<string> listed groups without an active room, for the caller to refresh
     */
    public function reconcileSchool(Tenant $tenant, string $provider, array $alreadyRefreshed = []): array
    {
        $schoolId = (string) $tenant->idp_school_id;
        $directory = $this->providers->directory($provider);

        if ($schoolId === '' || $directory->school($schoolId) === null) {
            Log::warning('IdP: school not visible, skipping group reconcile', [
                'tenant' => $tenant->instance_code,
                'idp_school_id' => $schoolId,
            ]);

            return [];
        }

        $listed = array_map(fn (IdpGroup $group): string => $group->id, $directory->groups($schoolId));

        $active = DB::table('au_rooms')
            ->whereNotNull('idp_group_id')
            ->where('status', '!=', RoomEnrolment::STATUS_ARCHIVED)
            ->pluck('idp_group_id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        foreach (array_diff($active, $listed) as $groupId) {
            $this->rooms->archiveRoom($groupId);

            Log::info('IdP: archived a room whose group is no longer listed', [
                'tenant' => $tenant->instance_code,
                'idp_group_id' => $groupId,
            ]);
        }

        return array_values(array_diff($listed, $active, $alreadyRefreshed));
    }

    /**
     * Reconcile room membership, carrying each member's role across.
     *
     * refreshGroup() imports missing members first, so every member normally
     * has a row here.
     *
     * @param  array{id: int, hash_id: string}  $room
     * @param  list<IdpUser>  $members
     */
    private function syncMembers(array $room, array $members, string $provider): void
    {
        $localIds = LegacyUser::whereIn('idp_user_id', array_map(fn (IdpUser $m): string => $m->id, $members))
            ->pluck('id', 'idp_user_id');

        $roles = [];
        $missing = 0;

        foreach ($members as $member) {
            $userId = $localIds->get($member->id);

            if ($userId === null) {
                $missing++;

                continue;
            }

            $roles[(int) $userId] = $this->roles->level($provider, $member->role);
        }

        if ($missing > 0) {
            Log::info('IdP: room has members aula does not hold yet', [
                'room_id' => $room['id'],
                'missing' => $missing,
            ]);
        }

        $this->rooms->syncRoomMembers($room['id'], $room['hash_id'], $roles);
    }
}
