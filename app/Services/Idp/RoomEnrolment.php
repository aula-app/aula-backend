<?php

declare(strict_types=1);

namespace App\Services\Idp;

use App\Services\Idp\Dto\IdpGroupRef;
use Illuminate\Support\Facades\DB;

/**
 * Rooms and room membership for directory-sourced data.
 *
 * A directory group ("Klasse 5a") is an aula room: an `au_rooms` row keyed by
 * `idp_group_id`, membership in `au_rel_rooms_users`, and a per-room role in
 * the `roles` JSON column on `au_users_basedata`. `au_groups` plays no part in
 * the integration.
 *
 * Shared by SchoolImport and by UserSync and GroupSync, so all three write the
 * same shape and a webhook arriving mid-import cannot diverge from it.
 *
 * Every method is idempotent: applying the same state twice changes nothing.
 */
final class RoomEnrolment
{
    public const int STATUS_ACTIVE = 1;

    public const int STATUS_ARCHIVED = 3;

    /**
     * Create or update the room for a directory group.
     *
     * @return array{id: int, hash_id: string}
     */
    public function upsertRoom(string $idpGroupId, string $name, bool $active = true): array
    {
        $existing = DB::table('au_rooms')
            ->where('idp_group_id', $idpGroupId)
            ->first(['id', 'hash_id', 'room_name', 'status']);

        $status = $active ? self::STATUS_ACTIVE : self::STATUS_ARCHIVED;

        if ($existing !== null) {
            if ($existing->room_name !== $name || (int) $existing->status !== $status) {
                DB::table('au_rooms')->where('id', $existing->id)->update([
                    'room_name' => $name,
                    'status' => $status,
                    'last_update' => now(),
                ]);
            }

            return ['id' => (int) $existing->id, 'hash_id' => (string) $existing->hash_id];
        }

        $hashId = md5($idpGroupId.(string) microtime(true).random_int(100, 10000000));

        $id = (int) DB::table('au_rooms')->insertGetId([
            'room_name' => $name,
            'status' => $status,
            'hash_id' => $hashId,
            'idp_group_id' => $idpGroupId,
            'type' => 0,
            'restrict_to_roomusers_only' => true,
            'order_importance' => 0,
            'updater_id' => 0,
        ]);

        return ['id' => $id, 'hash_id' => $hashId];
    }

    /**
     * Archive the room and remove every membership and `roles` entry for it,
     * aula-native members included.
     */
    public function archiveRoom(string $idpGroupId): bool
    {
        $existing = DB::table('au_rooms')->where('idp_group_id', $idpGroupId)->first(['id', 'hash_id']);

        if ($existing === null) {
            return false;
        }

        $roomId = (int) $existing->id;
        $hashId = (string) $existing->hash_id;

        DB::table('au_rooms')->where('id', $roomId)->update([
            'status' => self::STATUS_ARCHIVED,
            'last_update' => now(),
        ]);

        $memberIds = DB::table('au_rel_rooms_users')->where('room_id', $roomId)->pluck('user_id');

        foreach ($memberIds as $userId) {
            $this->unenrol((int) $userId, $roomId, $hashId);
        }

        // `roles` entries can outlive their membership row.
        $staleRoleIds = DB::table('au_users_basedata')
            ->where('roles', 'like', '%'.$hashId.'%')
            ->pluck('id');

        foreach ($staleRoleIds as $userId) {
            $this->writeRole((int) $userId, $hashId, null);
        }

        return true;
    }

    /**
     * Put a user in a room with a role, or update the role if already there.
     * Writes only what differs.
     */
    public function enrol(int $userId, int $roomId, string $roomHashId, int $role): void
    {
        $status = DB::table('au_rel_rooms_users')
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->value('status');

        $this->writeMembership($userId, $roomId, $status === null ? null : (int) $status);

        $this->writeRole($userId, $roomHashId, $role);
    }

    public function unenrol(int $userId, int $roomId, string $roomHashId): void
    {
        DB::table('au_rel_rooms_users')
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->delete();

        $this->writeRole($userId, $roomHashId, null);
    }

    /**
     * Make a user's directory-sourced room memberships match $groups exactly.
     *
     * Rooms with no `idp_group_id`, the school room and anything created inside
     * aula, are left alone.
     *
     * @param  list<IdpGroupRef>|list<string>  $groups  IdpGroupRefs, or bare provider group ids
     */
    public function syncUserRooms(int $userId, array $groups, int $role): void
    {
        $wantedGroupIds = array_map(
            fn ($group): string => $group instanceof IdpGroupRef ? $group->id : (string) $group,
            $groups,
        );

        $memberships = DB::table('au_rel_rooms_users')
            ->where('user_id', $userId)
            ->pluck('status', 'room_id')
            ->all();

        $roles = $this->decodeRoles(DB::table('au_users_basedata')->where('id', $userId)->value('roles'));

        foreach ($this->managedRooms() as $idpGroupId => $room) {
            $status = isset($memberships[$room['id']]) ? (int) $memberships[$room['id']] : null;

            if (in_array($idpGroupId, $wantedGroupIds, true)) {
                $this->writeMembership($userId, $room['id'], $status);

                if ($this->roleIn($roles, $room['hash_id']) !== $role) {
                    $this->writeRole($userId, $room['hash_id'], $role);
                }
            } elseif ($status !== null) {
                $this->unenrol($userId, $room['id'], $room['hash_id']);
            }
        }
    }

    /**
     * Make a room's membership match $userRoles exactly.
     *
     * Only rows carrying an `idp_user_id` are candidates for removal, so a user
     * added to the room inside aula stays.
     *
     * @param  array<int, int>  $userRoles  user id => role
     */
    public function syncRoomMembers(int $roomId, string $roomHashId, array $userRoles): void
    {
        $memberships = DB::table('au_rel_rooms_users')
            ->where('room_id', $roomId)
            ->pluck('status', 'user_id')
            ->all();

        $toRemove = DB::table('au_users_basedata')
            ->whereIn('id', array_keys($memberships) ?: [0])
            ->whereNotNull('idp_user_id')
            ->whereNotIn('id', array_keys($userRoles) ?: [0])
            ->pluck('id');

        foreach ($toRemove as $userId) {
            $this->unenrol((int) $userId, $roomId, $roomHashId);
        }

        $currentRoles = DB::table('au_users_basedata')
            ->whereIn('id', array_keys($userRoles) ?: [0])
            ->pluck('roles', 'id')
            ->all();

        foreach ($userRoles as $userId => $role) {
            $status = isset($memberships[$userId]) ? (int) $memberships[$userId] : null;

            $this->writeMembership((int) $userId, $roomId, $status);

            if ($this->roleIn($this->decodeRoles($currentRoles[$userId] ?? null), $roomHashId) !== (int) $role) {
                $this->writeRole((int) $userId, $roomHashId, (int) $role);
            }
        }
    }

    /**
     * Every directory-managed room, keyed by provider group id.
     *
     * @return array<string, array{id: int, hash_id: string}>
     */
    public function managedRooms(): array
    {
        $rooms = [];

        foreach (DB::table('au_rooms')->whereNotNull('idp_group_id')->get(['id', 'hash_id', 'idp_group_id']) as $room) {
            $rooms[(string) $room->idp_group_id] = [
                'id' => (int) $room->id,
                'hash_id' => (string) $room->hash_id,
            ];
        }

        return $rooms;
    }

    /**
     * Set or clear this user's role for one room in the `roles` JSON column.
     *
     * The column holds a list of {role, room hash_id} pairs; entries for other
     * rooms are left untouched.
     */
    private function writeRole(int $userId, string $roomHashId, ?int $role): void
    {
        $roles = $this->decodeRoles(DB::table('au_users_basedata')->where('id', $userId)->value('roles'));

        $others = array_values(array_filter(
            $roles,
            fn ($entry): bool => is_array($entry) && ($entry['room'] ?? null) !== $roomHashId,
        ));

        $updated = $role === null ? $others : [...$others, ['role' => $role, 'room' => $roomHashId]];

        $unchanged = $role === null
            ? count($others) === count($roles)
            : count($others) === count($roles) - 1 && $this->roleIn($roles, $roomHashId) === $role;

        if ($unchanged) {
            return;
        }

        DB::table('au_users_basedata')->where('id', $userId)->update([
            'roles' => json_encode($updated),
            'last_update' => now(),
        ]);
    }

    /**
     * Insert or reactivate a membership row; $status is the row's current
     * status, null when there is none.
     */
    private function writeMembership(int $userId, int $roomId, ?int $status): void
    {
        if ($status === self::STATUS_ACTIVE) {
            return;
        }

        DB::table('au_rel_rooms_users')->updateOrInsert(
            ['room_id' => $roomId, 'user_id' => $userId],
            ['status' => self::STATUS_ACTIVE, 'last_update' => now(), 'updater_id' => 0],
        );
    }

    /**
     * @return list<array{role?: int, room?: string}>
     */
    private function decodeRoles(mixed $json): array
    {
        $roles = json_decode((string) $json, true);

        return is_array($roles) ? array_values($roles) : [];
    }

    /**
     * @param  list<array{role?: int, room?: string}>  $roles
     */
    private function roleIn(array $roles, string $roomHashId): ?int
    {
        foreach ($roles as $entry) {
            if (is_array($entry) && ($entry['room'] ?? null) === $roomHashId) {
                return isset($entry['role']) ? (int) $entry['role'] : null;
            }
        }

        return null;
    }
}
