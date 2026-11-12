<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\Tenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Gives the regular users of a tenant who have no password of their own a
 * temporary one, and groups the credentials by room for handing out.
 *
 * A user who already holds a temporary password keeps it, so a second run
 * lists the same credentials instead of invalidating a list already handed out.
 *
 * @psalm-type Row = array{user_id: int, name: string, username: string, password: string, classes: string}
 * @psalm-type RoomOverview = array{key: string, room: string, new: int, existing: int}
 * @psalm-type Sheet = array{key: string, room_id: ?int, room: string, is_class: bool, rows: list<Row>}
 */
class TemporaryPasswordService
{
    public const string NO_ROOM_LABEL = 'Without room';

    public const string NO_ROOM_KEY = 'none';

    private const int PASSWORD_LENGTH = 8;

    // No 0/O, 1/l/I: the password gets read off paper.
    private const string PASSWORD_CHARS = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';

    private const int ROOM_ACTIVE = 1;

    private const int ROOM_TYPE_CLASS = 0;

    /**
     * @return array{new: int, existing: int} users who would get a new
     *                                        password, and users who already hold one
     */
    public function summary(Tenant $tenant): array
    {
        $users = $tenant->run(fn (): Collection => $this->eligibleUsers());
        $existing = $users->filter(fn (stdClass $user): bool => $this->holdsTemporaryPassword($user))->count();

        return ['new' => $users->count() - $existing, 'existing' => $existing];
    }

    /**
     * The rooms a sheet can be made for, without changing anything.
     *
     * @return list<RoomOverview>
     */
    public function overview(Tenant $tenant): array
    {
        return $tenant->run(function (): array {
            $users = $this->eligibleUsers();
            $passwords = $users
                ->mapWithKeys(fn (stdClass $user): array => [(int) $user->id => (string) $user->temp_pw])
                ->all();

            return array_map(function (array $sheet): array {
                $existing = count(array_filter($sheet['rows'], fn (array $row): bool => $row['password'] !== ''));

                return [
                    'key' => $sheet['key'],
                    'room' => $sheet['room'],
                    'new' => count($sheet['rows']) - $existing,
                    'existing' => $existing,
                ];
            }, $this->buildSheets($users, $passwords));
        });
    }

    /**
     * The temporary passwords already set, by room, without changing
     * anything. With a sheet key, only that room's sheet is returned.
     *
     * @return list<Sheet>
     */
    public function issued(Tenant $tenant, ?string $sheetKey = null): array
    {
        return $tenant->run(function () use ($sheetKey): array {
            $users = $this->eligibleUsers(sheetKey: $sheetKey)
                ->filter(fn (stdClass $user): bool => $this->holdsTemporaryPassword($user))
                ->values();
            $passwords = $users
                ->mapWithKeys(fn (stdClass $user): array => [(int) $user->id => (string) $user->temp_pw])
                ->all();

            return $this->only($this->buildSheets($users, $passwords), $sheetKey);
        });
    }

    /**
     * Sets the missing passwords and returns the sheets. With a sheet key,
     * only that room's users are touched and only its sheet is returned.
     *
     * @return array{sheets: list<Sheet>, new: int, existing: int}
     */
    public function issue(Tenant $tenant, ?string $sheetKey = null): array
    {
        return $tenant->run(function () use ($sheetKey): array {
            // Locked so a password chosen meanwhile is not overwritten, and
            // two runs cannot hand out different passwords for one user.
            [$users, $passwords, $new] = DB::transaction(function () use ($sheetKey): array {
                $users = $this->eligibleUsers(lock: true, sheetKey: $sheetKey);
                $pending = $users->reject(fn (stdClass $user): bool => $this->holdsTemporaryPassword($user));

                $passwords = $users
                    ->mapWithKeys(fn (stdClass $user): array => [(int) $user->id => (string) $user->temp_pw])
                    ->all();

                return [$users, array_replace($passwords, $this->assignPasswords($pending)), $pending->count()];
            });

            return [
                'sheets' => $this->only($this->buildSheets($users, $passwords), $sheetKey),
                'new' => $new,
                'existing' => $users->count() - $new,
            ];
        });
    }

    /**
     * Active regular users who never chose a password: they still hold a
     * temporary one, have none at all, or never used their set-password link.
     * SSO accounts log in through the IdP and are left out.
     *
     * `pw_changed` is not consulted: the legacy code never sets it.
     *
     * @return Collection<int, stdClass>
     */
    private function eligibleUsers(bool $lock = false, ?string $sheetKey = null): Collection
    {
        return DB::table('au_users_basedata')
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->when($sheetKey === self::NO_ROOM_KEY, fn (Builder $query) => $query
                ->whereNotExists(fn (Builder $sub) => $this->activeMembership($sub)))
            ->when($sheetKey !== null && $sheetKey !== self::NO_ROOM_KEY, fn (Builder $query) => $query
                ->whereExists(fn (Builder $sub) => $this->activeMembership($sub)->where('au_rooms.id', (int) $sheetKey)))
            ->where('status', UserStatus::Active->value)
            ->where('userlevel', UserLevel::User->value)
            ->whereNull('sso_sub')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $q) => $q->whereNotNull('temp_pw')->where('temp_pw', '!=', ''))
                ->orWhere(fn (Builder $q) => $q->whereNull('pw')->orWhere('pw', ''))
                // Adding a single user by email stores the hash of an empty
                // password, so `pw` is set although the user never chose one.
                ->orWhere(fn (Builder $q) => $q
                    ->whereNull('last_login')
                    ->whereExists(fn (Builder $sub) => $sub
                        ->selectRaw('1')
                        ->from('au_change_password')
                        ->whereColumn('au_change_password.user_id', 'au_users_basedata.id'))))
            ->orderBy('realname')
            ->orderBy('id')
            ->get(['id', 'realname', 'displayname', 'username', 'temp_pw']);
    }

    /**
     * @param  list<Sheet>  $sheets
     * @return list<Sheet>
     */
    private function only(array $sheets, ?string $sheetKey): array
    {
        if ($sheetKey === null) {
            return $sheets;
        }

        return array_values(array_filter($sheets, fn (array $sheet): bool => $sheet['key'] === $sheetKey));
    }

    /**
     * Correlated subquery: the user's memberships in active rooms.
     */
    private function activeMembership(Builder $query): Builder
    {
        return $query
            ->selectRaw('1')
            ->from('au_rel_rooms_users')
            ->join('au_rooms', 'au_rooms.id', '=', 'au_rel_rooms_users.room_id')
            ->whereColumn('au_rel_rooms_users.user_id', 'au_users_basedata.id')
            ->where('au_rel_rooms_users.status', 1)
            ->where('au_rooms.status', self::ROOM_ACTIVE);
    }

    private function holdsTemporaryPassword(stdClass $user): bool
    {
        return (string) $user->temp_pw !== '';
    }

    /**
     * Writes the same columns the legacy reset-password use case does:
     * `temp_pw` is what login compares the typed password against, `pw` is
     * cleared, and `refresh_token` makes an open session renew its JWT.
     * Runs inside the caller's transaction.
     *
     * @param  Collection<int, stdClass>  $users
     * @return array<int, string> user id => temporary password
     */
    private function assignPasswords(Collection $users): array
    {
        $passwords = $users
            ->mapWithKeys(fn (stdClass $user): array => [(int) $user->id => $this->generatePassword()])
            ->all();

        foreach ($passwords as $userId => $password) {
            DB::table('au_users_basedata')->where('id', $userId)->update([
                'temp_pw' => $password,
                'pw' => null,
                'pw_changed' => 0,
                'refresh_token' => true,
                'last_update' => now(),
            ]);
        }

        // The emailed set-password link would set `pw` without clearing
        // `temp_pw`, leaving the printed password valid for good.
        DB::table('au_change_password')->whereIn('user_id', array_keys($passwords))->delete();

        return $passwords;
    }

    /**
     * One sheet per active room with at least one listed user, then one for
     * the users in no active room.
     *
     * @param  Collection<int, stdClass>  $users
     * @param  array<int, string>  $passwords
     * @return list<Sheet>
     */
    private function buildSheets(Collection $users, array $passwords): array
    {
        $rooms = DB::table('au_rooms')
            ->where('status', self::ROOM_ACTIVE)
            ->orderBy('room_name')
            ->orderBy('id')
            ->get(['id', 'room_name', 'type']);

        $memberships = DB::table('au_rel_rooms_users')
            ->where('status', 1)
            ->whereIn('room_id', $rooms->pluck('id'))
            ->whereIn('user_id', $users->pluck('id'))
            ->get(['room_id', 'user_id']);

        $classNames = $this->classNamesByUser($rooms, $memberships);
        $usersById = $users->keyBy('id');
        $sheets = [];
        foreach ($rooms as $room) {
            $members = $memberships->where('room_id', $room->id)->pluck('user_id')->unique()
                ->map(fn (mixed $id): stdClass => $usersById[$id]);

            if ($members->isEmpty()) {
                continue;
            }

            $sheets[] = [
                'key' => (string) $room->id,
                'room_id' => (int) $room->id,
                'room' => (string) $room->room_name,
                'is_class' => (int) $room->type === self::ROOM_TYPE_CLASS,
                'rows' => $this->rows($members, $passwords, $classNames),
            ];
        }

        $roomless = $users->whereNotIn('id', $memberships->pluck('user_id')->all());
        if ($roomless->isNotEmpty()) {
            $sheets[] = [
                'key' => self::NO_ROOM_KEY,
                'room_id' => null,
                'room' => self::NO_ROOM_LABEL,
                'is_class' => false,
                'rows' => $this->rows($roomless, $passwords, $classNames),
            ];
        }

        return $sheets;
    }

    /**
     * @param  iterable<stdClass>  $members
     * @param  array<int, string>  $passwords
     * @param  array<int, string>  $classNames
     * @return list<Row>
     */
    private function rows(iterable $members, array $passwords, array $classNames): array
    {
        $rows = [];
        foreach ($members as $user) {
            $rows[] = [
                'user_id' => (int) $user->id,
                'name' => $this->fullName($user),
                'username' => (string) $user->username,
                'password' => $passwords[(int) $user->id],
                'classes' => $classNames[(int) $user->id] ?? '',
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp(mb_strtolower($a['name']), mb_strtolower($b['name'])));

        return $rows;
    }

    /**
     * The "class" of a user is the standard rooms they belong to. A directory
     * group such as "Klasse 5a" is imported as a standard room, so this names
     * the class even on the main room's sheet, where every user appears.
     *
     * @param  Collection<int, stdClass>  $rooms
     * @param  Collection<int, stdClass>  $memberships
     * @return array<int, string> user id => comma separated room names
     */
    private function classNamesByUser(Collection $rooms, Collection $memberships): array
    {
        $classRooms = $rooms->where('type', self::ROOM_TYPE_CLASS)->keyBy('id');

        return $memberships
            ->filter(fn (stdClass $m): bool => $classRooms->has($m->room_id))
            ->groupBy('user_id')
            ->map(fn (Collection $rows): string => $rows
                ->map(fn (stdClass $m): string => (string) $classRooms[$m->room_id]->room_name)
                ->unique()
                ->sort()
                ->implode(', '))
            ->all();
    }

    private function fullName(stdClass $user): string
    {
        return trim((string) ($user->realname ?: $user->displayname ?: $user->username));
    }

    private function generatePassword(): string
    {
        $max = strlen(self::PASSWORD_CHARS) - 1;

        do {
            $password = '';
            for ($i = 0; $i < self::PASSWORD_LENGTH; $i++) {
                $password .= self::PASSWORD_CHARS[random_int(0, $max)];
            }
        } while (! preg_match('/\d/', $password) || ! preg_match('/[a-z]/', $password) || ! preg_match('/[A-Z]/', $password));

        return $password;
    }
}
