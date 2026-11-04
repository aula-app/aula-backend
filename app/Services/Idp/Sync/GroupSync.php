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

    /**
     * Refresh the room from upstream, or archive it when the group is gone.
     *
     * Because events can come out of order, we don't look at event data, but
     * always use the idp directory as the source of the truth.
     *
     * `/groups/{id}` answers 404 both for a group unshared from the app and,
     * presumably, for a school deleted or disconnected from it. We want to archive
     * the room only when a group is unshared, and skip updating the room when the school is gone.
     */
    public function handle(Tenant $tenant, string $provider, string $groupId): SyncOutcome
    {
        Log::withContext(['idp_group_id' => $groupId]);

        if ($this->refreshGroup($groupId, $tenant, $provider)) {
            return SyncOutcome::processed();
        }

        if (! $this->schoolVisible($tenant, $provider)) {
            Log::warning('IdP: school not visible, not archiving the room of a missing group');

            return SyncOutcome::skipped('school_not_visible');
        }

        return $this->rooms->archiveRoom($groupId)
            ? SyncOutcome::processed()
            : SyncOutcome::skipped('idp_group_not_found_in_aula');
    }

    /**
     * Converge one room on the group read back from the directory: name,
     * status, members and the initial per-room role of new members. Members
     * without a local row are imported.
     *
     * An inactive group archives the room. Returns false when the group is
     * gone upstream.
     */
    private function refreshGroup(string $groupId, Tenant $tenant, string $provider): bool
    {
        $group = $this->providers->directory($provider)->group($groupId);

        if ($group === null) {
            return false;
        }

        if (! $group->isActive()) {
            $this->rooms->archiveRoom($group->id);

            return true;
        }

        // The room is upserted first, and importMember() below enrols each new member.
        /** @var array{id: int, hash_id: string} **/
        $room = $this->rooms->upsertRoom($group->id, $group->name);

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
            Log::info("IdP: imported [{$imported}] new users from [{$provider}] based on group refresh", [
                'room_id' => $room['id'],
                'idp_group_id' => $group->id,
                'imported' => $imported,
                'provider' => $provider
            ]);
        }

        return true;
    }

    /**
     * Converge the tenant's rooms on the school's group list.
     *
     * Eduplaces sends no group events, so a group added to the sync, emptied
     * or deleted upstream surfaces only here. An active room whose group is no
     * longer listed is archived.
     *
     * Skipped when the school itself is not visible: a school disconnected
     * from the app lists no groups, which must not archive every room.
     */
    public function reconcileSchool(Tenant $tenant, string $provider): void
    {
        $schoolId = (string) $tenant->idp_school_id;
        if (! $this->schoolVisible($tenant, $provider)) {
            Log::warning('IdP: school not visible, skipping group reconcile');

            return;
        }

        $directory = $this->providers->directory($provider);
        $directoryIdpGroupIds = array_map(fn (IdpGroup $group): string => $group->id, $directory->groups($schoolId));

        $activeIdpGroupIds = DB::table('au_rooms')
            ->whereNotNull('idp_group_id')
            ->where('status', '!=', RoomEnrolment::STATUS_ARCHIVED)
            ->pluck('idp_group_id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        $toArchive = array_diff($activeIdpGroupIds, $directoryIdpGroupIds);
        foreach ($toArchive as $groupId) {
            $this->rooms->archiveRoom($groupId);

            Log::info('IdP: archived a room whose group is no longer listed', [
                'idp_group_id' => $groupId,
            ]);
        }

        $toUnarchiveOrAdd = array_values(array_diff($directoryIdpGroupIds, $activeIdpGroupIds));
        foreach ($toUnarchiveOrAdd as $groupId) {
            $outcome = $this->handle($tenant, $provider, $groupId);

            if (! $outcome->wasProcessed) {
                Log::info('IdP: group refresh changed nothing', [
                    'idp_group_id' => $groupId,
                    'reason' => $outcome->reason,
                ]);
            }
        }
    }

    /**
     * Whether `/schools/{id}` still answers for the tenant's school. A school
     * deleted or disconnected from the app does not, and nothing may be
     * archived on its behalf.
     */
    private function schoolVisible(Tenant $tenant, string $provider): bool
    {
        $schoolId = (string) $tenant->idp_school_id;

        return $schoolId !== '' && $this->providers->directory($provider)->school($schoolId) !== null;
    }

    /**
     * Reconcile aula-Room membership with provider, map each member's role into aula-Userlevel.
     *
     * refreshGroup() imports missing members first, so every member normally
     * has a row here.
     *
     * @param  array{id: int, hash_id: string}  $room
     * @param  list<IdpUser>  $members
     */
    private function syncMembers(array $room, array $members, string $provider): void
    {
        $membersIdpUserIds = array_map(fn (IdpUser $m): string => $m->id, $members);
        $localIds = LegacyUser::whereIn('idp_user_id', $membersIdpUserIds)
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
            Log::info("IdP: room from [{$provider}] has [{$missing}] members that are missing from aula-Rooms", [
                'room_id' => $room['id'],
                'missing' => $missing,
                'provider' => $provider,
            ]);
        }

        $this->rooms->syncRoomMembers($room['id'], $room['hash_id'], $roles);
    }
}
