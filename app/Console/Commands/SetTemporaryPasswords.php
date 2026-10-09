<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use stdClass;

/**
 * Hands every active non-admin of a tenant a fresh temporary password and
 * writes one CSV per room, so a teacher can hand the credentials out class by
 * class.
 *
 * Writes the same columns the legacy reset-password use case does: `temp_pw`
 * is what login compares the typed password against, `pw` is cleared so the
 * old password stops working, and `refresh_token` makes an open session renew
 * its JWT.
 */
class SetTemporaryPasswords extends Command
{
    private const PASSWORD_LENGTH = 8;

    // No 0/O, 1/l/I: the password gets read off paper.
    private const PASSWORD_CHARS = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';

    private const CSV_HEADER = ['Full name', 'Username', 'Temporary password', 'Class'];

    protected $signature = 'tenant:temp-passwords
                            {instance_code : Instance code of the tenant}
                            {--output= : Directory for the CSV files (default: storage/app/temp-passwords/<code>/<timestamp>)}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Set a random temporary password for every active non-admin user and write one CSV per room';

    public function handle(): int
    {
        $code = (string) $this->argument('instance_code');
        $tenant = Tenant::where('instance_code', $code)->first();

        if ($tenant === null) {
            $this->error("No tenant with instance code [{$code}].");

            return self::FAILURE;
        }

        $outputDir = $this->option('output')
            ?: storage_path('app/temp-passwords/'.$code.'/'.now()->format('Y-m-d_His'));

        [$users, $rooms] = $tenant->run(fn (): array => [$this->eligibleUsers(), $this->activeRooms()]);

        $this->line("Tenant: <info>{$tenant->name}</info> ({$code})");
        $this->table(['', 'count'], [
            ['active non-admin users (passwords will be reset)', $users->count()],
            ['active rooms', $rooms->count()],
        ]);
        $this->line("CSV files go to: {$outputDir}");

        if ($users->isEmpty()) {
            $this->warn('Nothing to do.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Reset the passwords of these users?')) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $passwords = $tenant->run(fn (): array => $this->resetPasswords($users));
        $written = $tenant->run(fn (): array => $this->writeCsvFiles($rooms, $users, $passwords, $outputDir));

        $this->info("Reset {$users->count()} passwords. Wrote ".count($written).' CSV file(s):');
        foreach ($written as $file) {
            $this->line("  {$file}");
        }
        $this->warn('The files hold passwords in plain text. Delete them once handed out.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, stdClass>
     */
    private function eligibleUsers(): Collection
    {
        return DB::table('au_users_basedata')
            ->where('status', UserStatus::Active->value)
            ->where('userlevel', '<', UserLevel::Admin->value)
            ->orderBy('realname')
            ->orderBy('id')
            ->get(['id', 'realname', 'displayname', 'username']);
    }

    /**
     * @return Collection<int, stdClass>
     */
    private function activeRooms(): Collection
    {
        return DB::table('au_rooms')
            ->where('status', 1)
            ->orderBy('room_name')
            ->orderBy('id')
            ->get(['id', 'room_name', 'type']);
    }

    /**
     * @param  Collection<int, stdClass>  $users
     * @return array<int, string> user id => temporary password
     */
    private function resetPasswords(Collection $users): array
    {
        $passwords = $users->mapWithKeys(fn (stdClass $user) => [(int) $user->id => $this->generatePassword()])->all();

        DB::transaction(function () use ($passwords): void {
            foreach ($passwords as $userId => $password) {
                DB::table('au_users_basedata')->where('id', $userId)->update([
                    'temp_pw' => $password,
                    'pw' => null,
                    'pw_changed' => 0,
                    'refresh_token' => true,
                    'last_update' => now(),
                ]);
            }
        });

        return $passwords;
    }

    /**
     * One file per active room that has at least one eligible member. Empty
     * rooms get no file.
     *
     * @param  Collection<int, stdClass>  $rooms
     * @param  Collection<int, stdClass>  $users
     * @param  array<int, string>  $passwords
     * @return list<string> paths written
     */
    private function writeCsvFiles(Collection $rooms, Collection $users, array $passwords, string $outputDir): array
    {
        $memberships = DB::table('au_rel_rooms_users')
            ->where('status', 1)
            ->whereIn('room_id', $rooms->pluck('id'))
            ->whereIn('user_id', $users->pluck('id'))
            ->get(['room_id', 'user_id']);

        $classNames = $this->classNamesByUser($rooms, $memberships);
        $usersById = $users->keyBy('id');
        $written = [];

        foreach ($rooms as $room) {
            $memberIds = $memberships->where('room_id', $room->id)->pluck('user_id');

            if ($memberIds->isEmpty()) {
                continue;
            }

            $rows = $memberIds
                ->map(fn ($id) => $usersById[$id])
                ->sortBy(fn (stdClass $user) => $this->fullName($user))
                ->map(fn (stdClass $user): array => [
                    $this->fullName($user),
                    (string) $user->username,
                    $passwords[(int) $user->id],
                    $classNames[(int) $user->id] ?? '',
                ]);

            $path = $outputDir.'/'.Str::slug((string) $room->room_name).'-'.$room->id.'.csv';
            $this->writeCsv($path, $rows->values()->all());
            $written[] = $path;
        }

        return $written;
    }

    /**
     * The "class" of a user is the standard rooms they belong to. A directory
     * group such as "Klasse 5a" is imported as a standard room, so this names
     * the class even in the main room's file, where every user appears.
     *
     * @param  Collection<int, stdClass>  $rooms
     * @param  Collection<int, stdClass>  $memberships
     * @return array<int, string> user id => comma separated room names
     */
    private function classNamesByUser(Collection $rooms, Collection $memberships): array
    {
        $classRooms = $rooms->where('type', 0)->keyBy('id');

        return $memberships
            ->filter(fn (stdClass $m) => $classRooms->has($m->room_id))
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows
                ->map(fn (stdClass $m) => (string) $classRooms[$m->room_id]->room_name)
                ->sort()
                ->implode(', '))
            ->all();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Cannot write {$path}");
        }

        // BOM so Excel reads umlauts as UTF-8; ';' is what Excel expects in a
        // German locale and what the legacy CSV import uses.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::CSV_HEADER, ';', '"', '\\', "\n");
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', '\\', "\n");
        }
        fclose($handle);
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
