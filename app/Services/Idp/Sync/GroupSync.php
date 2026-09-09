<?php

declare(strict_types=1);

namespace App\Services\Idp\Sync;

use App\Models\IdpDirectoryEntry;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\Idp\Dto\IdpEvent;
use App\Services\Idp\Dto\IdpUser;
use App\Services\Idp\IdpProviders;
use App\Services\Idp\RoleMap;
use App\Services\Idp\RoomEnrolment;
use App\Services\Idp\TenantResolver;
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
        private readonly TenantResolver $resolver,
        private readonly RoleMap $roles,
    ) {}

    public function handle(IdpEvent $event, Tenant $tenant, string $provider): SyncOutcome
    {
        if ($event->action === IdpEvent::ACTION_DELETE) {
            return $this->rooms->archiveRoom($event->entityId)
                ? SyncOutcome::processed()
                : SyncOutcome::skipped('room_not_local');
        }

        $group = $this->providers->directory($provider)->group($event->entityId);

        if ($group === null) {
            return SyncOutcome::skipped('group_not_found_upstream');
        }

        $active = $event->action === IdpEvent::ACTION_RESTORE || $group->isActive();

        $room = $this->rooms->upsertRoom($group->id, $group->name, $active);

        $this->syncMembers($room, $group->members, $provider);

        $this->resolver->remember(IdpDirectoryEntry::TYPE_GROUP, $group->id, $tenant->id);
        $this->resolver->rememberMany(IdpDirectoryEntry::TYPE_USER, $group->memberIds(), $tenant->id);

        return SyncOutcome::processed();
    }

    /**
     * Reconcile room membership, carrying each member's role across.
     *
     * A member with no `idp_user_id` row is counted and skipped, not created: a
     * group payload is thinner than a user record, and that member's own user
     * event builds the row and reconciles this room from the other side.
     *
     * @param  array{id: int, hash_id: string}  $room
     * @param  list<IdpUser>  $members
     */
    private function syncMembers(array $room, array $members, string $provider): void
    {
        $roles = [];
        $missing = 0;

        foreach ($members as $member) {
            $userId = LegacyUser::where('idp_user_id', $member->id)->value('id');

            if ($userId === null) {
                $missing++;

                continue;
            }

            $roles[(int) $userId] = $this->roles->roomRole($provider, $member->role);

            $this->backfillName((int) $userId, $member);
        }

        if ($missing > 0) {
            Log::info('IdP: room has members aula does not hold yet', [
                'room_id' => $room['id'],
                'missing' => $missing,
            ]);
        }

        $this->rooms->syncRoomMembers($room['id'], $room['hash_id'], $roles);
    }

    /**
     * Write `realname` once a group event carries one.
     *
     * A provider can expose real names on group members only, so a user
     * imported from the user listing alone can still be showing a pseudonym.
     */
    private function backfillName(int $userId, IdpUser $member): void
    {
        $real = $member->realName();

        if ($real === null) {
            return;
        }

        $user = LegacyUser::find($userId);

        if ($user === null || $user->realname === $real) {
            return;
        }

        $user->realname = $real;
        $user->displayname = $member->displayName();
        $user->save();
    }
}
