<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\LegacyUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * Covers `tenant:temp-passwords`, which gives the active regular users of a
 * tenant who never chose a password a temporary one and writes one CSV per
 * room so the credentials can be handed out class by class.
 */
class TempPasswordsCommandTest extends TestCase
{
    use CreatesTestTenant;

    private const PREFIX = 'tmppw.';

    // bcrypt of 'old-password'
    private const OWN_PASSWORD_HASH = '$2y$04$f1jyZBATxL0lx61QcJfV7OKqY9cjSZ6U89.GDYrT96WEo3aACYeSO';

    private string $outputDir;

    /** @var array<string, int> room name => id */
    private array $rooms = [];

    /** @var array<string, int> username suffix => id */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        $this->clean();

        $this->outputDir = sys_get_temp_dir().'/tmppw-'.bin2hex(random_bytes(4));
        $this->seedSchool();
    }

    protected function tearDown(): void
    {
        $this->clean();
        File::deleteDirectory($this->outputDir);
        parent::tearDown();
    }

    public function test_it_gives_users_without_a_password_a_temporary_one(): void
    {
        $this->run_command();

        self::$testTenant->run(function () {
            $student = LegacyUser::find($this->users['student1']);
            $other = LegacyUser::find($this->users['student2']);

            $this->assertNotEmpty($student->temp_pw);
            $this->assertNull($student->pw);
            $this->assertSame(0, (int) $student->pw_changed);
            $this->assertTrue($student->refresh_token);
            $this->assertNotSame($student->temp_pw, $other->temp_pw);

            // Login compares temp_pw by equality, so what the CSV says must
            // be what the user types.
            $this->assertTrue($student->checkPassword($student->temp_pw));
        });
    }

    public function test_it_leaves_moderators_admins_and_inactive_users_alone(): void
    {
        $untouched = ['teacher', 'admin', 'inactive', 'hasPassword', 'sso'];
        $before = self::$testTenant->run(fn () => LegacyUser::whereIn('id', array_map(fn ($k) => $this->users[$k], $untouched))
            ->pluck('pw', 'id')
            ->all());

        $this->run_command();

        self::$testTenant->run(function () use ($before, $untouched) {
            foreach ($untouched as $key) {
                $user = LegacyUser::find($this->users[$key]);

                $this->assertSame($before[$user->id], $user->pw, "{$key} password changed");
                $this->assertEmpty($user->temp_pw, "{$key} got a temp password");
            }
        });
    }

    public function test_it_leaves_a_user_who_chose_a_password_out_of_the_csv(): void
    {
        $this->run_command();

        $usernames = array_column(array_slice($this->readCsv($this->rooms['Klasse 5a']), 1), 1);

        $this->assertNotContains(self::PREFIX.'hasPassword', $usernames);
        $this->assertNotContains(self::PREFIX.'sso', $usernames);
    }

    public function test_it_keeps_a_temporary_password_already_issued(): void
    {
        $this->run_command();
        $first = self::$testTenant->run(fn () => LegacyUser::find($this->users['student1'])->temp_pw);

        File::deleteDirectory($this->outputDir);
        $this->run_command();

        $second = self::$testTenant->run(fn () => LegacyUser::find($this->users['student1'])->temp_pw);
        $this->assertSame($first, $second);

        $row = collect(array_slice($this->readCsv($this->rooms['Klasse 5a']), 1))
            ->firstWhere(fn (array $r) => $r[1] === self::PREFIX.'student1');
        $this->assertSame($first, $row[2]);
    }

    public function test_it_resets_a_user_who_never_used_the_set_password_link(): void
    {
        // Adding a single user by email stores the hash of an empty password
        // and a pending set-password secret.
        $id = $this->seedUser('pending', 'Fritz Wartend', UserLevel::User, pw: password_hash('', PASSWORD_BCRYPT));
        $this->enrol($this->rooms['Klasse 5a'], $id);
        self::$testTenant->run(fn () => DB::table('au_change_password')->insert([
            'user_id' => $id,
            'secret' => self::PREFIX.'secret',
        ]));

        $this->run_command();

        self::$testTenant->run(function () use ($id) {
            $user = LegacyUser::find($id);
            $this->assertNotEmpty($user->temp_pw);
            $this->assertNull($user->pw);
            // The link would set a password without clearing temp_pw.
            $this->assertFalse(DB::table('au_change_password')->where('user_id', $id)->exists());
        });
    }

    public function test_a_user_in_no_room_gets_a_sheet_of_their_own(): void
    {
        $this->seedUser('roomless', 'Gerda Ohne', UserLevel::User, pw: null);

        $this->run_command();

        $file = $this->outputDir.'/without-room.csv';
        $this->assertFileExists($file);
        $this->assertStringContainsString(self::PREFIX.'roomless', File::get($file));
    }

    public function test_it_writes_one_csv_per_room_that_has_members(): void
    {
        $this->run_command();

        // The shared test tenant may hold other users, who land in a
        // without-room file; "Leerer Raum" has no members and gets none.
        $files = collect(File::files($this->outputDir))->map->getFilename()
            ->reject(fn (string $name) => $name === 'without-room.csv')
            ->sort()->values();

        $this->assertCount(2, $files);
        $this->assertStringStartsWith('klasse-5a-', $files[0]);
        $this->assertStringStartsWith('klasse-6b-', $files[1]);
    }

    public function test_the_csv_lists_members_with_their_password_and_class(): void
    {
        $this->run_command();

        $passwords = self::$testTenant->run(fn () => LegacyUser::whereIn('id', $this->users)
            ->pluck('temp_pw', 'username')
            ->all());

        $rows = $this->readCsv($this->rooms['Klasse 5a']);

        $this->assertSame(['Full name', 'Username', 'Temporary password', 'Class'], $rows[0]);

        $byUsername = collect(array_slice($rows, 1))->keyBy(fn (array $row) => $row[1]);

        // The admin and the inactive user are also in 5a and must not appear.
        $this->assertSame([self::PREFIX.'student1', self::PREFIX.'student2'], $byUsername->keys()->sort()->values()->all());

        $student1 = $byUsername[self::PREFIX.'student1'];
        $this->assertSame('Anna Erste', $student1[0]);
        $this->assertSame($passwords[self::PREFIX.'student1'], $student1[2]);
        $this->assertSame('Klasse 5a', $student1[3]);

        // A user in two classes lists both, whichever room's CSV it is.
        $student2 = $byUsername[self::PREFIX.'student2'];
        $this->assertSame('Klasse 5a, Klasse 6b', $student2[3]);
    }

    public function test_a_moderator_does_not_appear_in_the_room_csv(): void
    {
        $this->run_command();

        $usernames = array_column(array_slice($this->readCsv($this->rooms['Klasse 6b']), 1), 1);

        // The teacher (moderator) is in 6b alongside student2.
        $this->assertSame([self::PREFIX.'student2'], $usernames);
    }

    public function test_the_main_room_csv_still_names_the_class(): void
    {
        $mainId = $this->seedRoom('Aula', type: 1);
        $this->enrol($mainId, $this->users['student1']);

        $this->run_command();

        $rows = $this->readCsv($mainId);
        $student1 = collect(array_slice($rows, 1))->firstWhere(fn (array $row) => $row[1] === self::PREFIX.'student1');

        $this->assertSame('Klasse 5a', $student1[3]);
    }

    public function test_it_does_nothing_without_confirmation(): void
    {
        $this->artisan('tenant:temp-passwords', [
            'instance_code' => 'TEST001',
            '--output' => $this->outputDir,
        ])
            ->expectsConfirmation('Set temporary passwords for these users?', 'no')
            ->assertExitCode(0);

        self::$testTenant->run(function () {
            $this->assertEmpty(LegacyUser::find($this->users['student1'])->temp_pw);
        });
        $this->assertDirectoryDoesNotExist($this->outputDir);
    }

    public function test_it_fails_on_an_unknown_instance_code(): void
    {
        $this->artisan('tenant:temp-passwords', ['instance_code' => 'NOPE', '--force' => true])
            ->assertExitCode(1);
    }

    private function run_command(): void
    {
        $this->artisan('tenant:temp-passwords', [
            'instance_code' => 'TEST001',
            '--output' => $this->outputDir,
            '--force' => true,
        ])->assertExitCode(0);
    }

    /**
     * @return list<list<string>>
     */
    private function readCsv(int $roomId): array
    {
        $file = collect(File::files($this->outputDir))
            ->first(fn ($f) => str_ends_with($f->getFilename(), "-{$roomId}.csv"));

        $this->assertNotNull($file, "No CSV written for room {$roomId}");

        $content = preg_replace('/^\xEF\xBB\xBF/', '', File::get($file->getPathname()));

        return array_map(
            fn (string $line) => str_getcsv($line, ';', '"', '\\'),
            array_filter(explode("\n", $content), fn (string $l) => $l !== ''),
        );
    }

    private function seedSchool(): void
    {
        $a = $this->seedRoom('Klasse 5a');
        $b = $this->seedRoom('Klasse 6b');
        $this->seedRoom('Leerer Raum');

        $this->users = [
            'student1' => $this->seedUser('student1', 'Anna Erste', UserLevel::User, pw: null),
            'student2' => $this->seedUser('student2', 'Ben Zweiter', UserLevel::User, pw: null),
            'hasPassword' => $this->seedUser('hasPassword', 'Hanna Fertig', UserLevel::User),
            'sso' => $this->seedUser('sso', 'Ida Extern', UserLevel::User, pw: null, ssoSub: self::PREFIX.'sub'),
            'teacher' => $this->seedUser('teacher', 'Carla Lehrerin', UserLevel::Moderator),
            'admin' => $this->seedUser('admin', 'Dora Admin', UserLevel::Admin),
            'inactive' => $this->seedUser('inactive', 'Emil Weg', UserLevel::User, UserStatus::Inactive),
        ];

        $this->enrol($a, $this->users['student1']);
        $this->enrol($a, $this->users['student2']);
        $this->enrol($a, $this->users['hasPassword']);
        $this->enrol($a, $this->users['sso']);
        $this->enrol($a, $this->users['admin']);
        $this->enrol($a, $this->users['inactive']);
        $this->enrol($b, $this->users['student2']);
        $this->enrol($b, $this->users['teacher']);
    }

    private function seedRoom(string $name, int $type = 0): int
    {
        $id = (int) self::$testTenant->run(fn () => DB::table('au_rooms')->insertGetId([
            'room_name' => $name,
            'status' => 1,
            'hash_id' => self::PREFIX.md5($name),
            'type' => $type,
        ]));

        $this->rooms[$name] = $id;

        return $id;
    }

    private function seedUser(
        string $suffix,
        string $realname,
        UserLevel $level,
        UserStatus $status = UserStatus::Active,
        ?string $pw = self::OWN_PASSWORD_HASH,
        ?string $ssoSub = null,
    ): int {
        return (int) self::$testTenant->run(function () use ($suffix, $realname, $level, $status, $pw, $ssoSub) {
            $user = new LegacyUser;
            $user->username = self::PREFIX.$suffix;
            $user->realname = $realname;
            $user->displayname = $realname;
            $user->email = $suffix.'@tmppw.example';
            $user->pw = $pw;
            $user->sso_sub = $ssoSub;
            $user->last_login = $pw === self::OWN_PASSWORD_HASH ? now() : null;
            $user->status = $status;
            $user->userlevel = $level;
            $user->hash_id = md5($user->username);
            $user->save();

            return $user->id;
        });
    }

    private function enrol(int $roomId, int $userId): void
    {
        self::$testTenant->run(fn () => DB::table('au_rel_rooms_users')->insert([
            'room_id' => $roomId,
            'user_id' => $userId,
            'status' => 1,
        ]));
    }

    private function clean(): void
    {
        self::$testTenant->run(function () {
            $userIds = DB::table('au_users_basedata')->where('username', 'like', self::PREFIX.'%')->pluck('id');
            $roomIds = DB::table('au_rooms')->where('hash_id', 'like', self::PREFIX.'%')->pluck('id');

            DB::table('au_rel_rooms_users')->whereIn('user_id', $userIds)->orWhereIn('room_id', $roomIds)->delete();
            DB::table('au_change_password')->whereIn('user_id', $userIds)->delete();
            DB::table('au_users_basedata')->whereIn('id', $userIds)->delete();
            DB::table('au_rooms')->whereIn('id', $roomIds)->delete();
        });
    }
}
