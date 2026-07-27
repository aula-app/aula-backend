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
 * A directory group is an aula **room**, so this keeps `au_rooms` and
 * `au_rel_rooms_users` in step after the initial import. It shares the same
 * enrolment code as SchoolImport, so the two cannot produce different shapes.
 *
 * Must be called with tenancy already initialised.
 *
 * Converges on the state read back from the directory rather than applying the
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
     * Reconcile who is in the room, carrying each member's role across.
     *
     * Members aula has never seen are left out rather than invented: a group
     * payload is usually thinner than a user record, so the row their own user
     * event builds is better, and that event reconciles this room from the
     * other side.
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
     * Fill in a real name once a group event reveals one.
     *
     * Some providers expose real names only on group members, so a user
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
