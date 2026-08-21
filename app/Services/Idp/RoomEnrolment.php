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
            ->first(['id', 'hash_id']);

        $status = $active ? self::STATUS_ACTIVE : self::STATUS_ARCHIVED;

        if ($existing !== null) {
            DB::table('au_rooms')->where('id', $existing->id)->update([
                'room_name' => $name,
                'status' => $status,
                'last_update' => now(),
            ]);

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

    public function archiveRoom(string $idpGroupId): bool
    {
        $existing = DB::table('au_rooms')->where('idp_group_id', $idpGroupId)->first(['id']);

        if ($existing === null) {
            return false;
        }

        DB::table('au_rooms')->where('id', $existing->id)->update([
            'status' => self::STATUS_ARCHIVED,
            'last_update' => now(),
        ]);

        return true;
    }

    /**
     * Put a user in a room with a role, or update the role if already there.
     */
    public function enrol(int $userId, int $roomId, string $roomHashId, int $role): void
    {
        DB::table('au_rel_rooms_users')->updateOrInsert(
            ['room_id' => $roomId, 'user_id' => $userId],
            ['status' => self::STATUS_ACTIVE, 'last_update' => now(), 'updater_id' => 0],
        );

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

        $managed = $this->managedRooms();

        foreach ($managed as $idpGroupId => $room) {
            $shouldBeIn = in_array($idpGroupId, $wantedGroupIds, true);
            $isIn = DB::table('au_rel_rooms_users')
                ->where('room_id', $room['id'])
                ->where('user_id', $userId)
                ->exists();

            if ($shouldBeIn) {
                $this->enrol($userId, $room['id'], $room['hash_id'], $role);
            } elseif ($isIn) {
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
        $managedUserIds = DB::table('au_users_basedata')
            ->whereNotNull('idp_user_id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $wanted = array_keys($userRoles);

        $toRemove = DB::table('au_rel_rooms_users')
            ->where('room_id', $roomId)
            ->whereIn('user_id', $managedUserIds ?: [0])
            ->whereNotIn('user_id', $wanted ?: [0])
            ->pluck('user_id');

        foreach ($toRemove as $userId) {
            $this->unenrol((int) $userId, $roomId, $roomHashId);
        }

        foreach ($userRoles as $userId => $role) {
            $this->enrol((int) $userId, $roomId, $roomHashId, (int) $role);
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
        $current = DB::table('au_users_basedata')->where('id', $userId)->value('roles');

        /** @var list<array{role?: int, room?: string}> $roles */
        $roles = json_decode((string) $current, true) ?: [];

        $roles = array_values(array_filter(
            $roles,
            fn ($entry): bool => is_array($entry) && ($entry['room'] ?? null) !== $roomHashId,
        ));

        if ($role !== null) {
            $roles[] = ['role' => $role, 'room' => $roomHashId];
        }

        DB::table('au_users_basedata')->where('id', $userId)->update([
            'roles' => json_encode($roles),
            'last_update' => now(),
        ]);
    }
}
