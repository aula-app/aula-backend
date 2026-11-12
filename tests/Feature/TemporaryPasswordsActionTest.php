<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\LegacyUser;
use App\Services\TemporaryPasswordExport;
use App\Services\TemporaryPasswordPdf;
use App\Services\TemporaryPasswordService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * Covers what the manager panel's temporary-passwords page downloads: the
 * per-room sheets, their PDF, and the ZIP of all rooms.
 */
class TemporaryPasswordsActionTest extends TestCase
{
    use CreatesTestTenant;

    private const PREFIX = 'tmppdf.';

    private int $classId;

    private int $mainId;

    private int $studentId;

    private int $mainOnlyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        $this->clean();

        $this->classId = $this->seedRoom('Klasse 7c', type: 0);
        $this->mainId = $this->seedRoom('Aula', type: 1);
        $this->studentId = $this->seedUser('student', 'Jörg Müller');
        $this->mainOnlyId = $this->seedUser('mainonly', 'Käthe Allein');

        $this->enrol($this->classId, $this->studentId);
        $this->enrol($this->mainId, $this->studentId);
        $this->enrol($this->mainId, $this->mainOnlyId);
    }

    protected function tearDown(): void
    {
        $this->clean();
        parent::tearDown();
    }

    public function test_the_overview_counts_users_per_room_without_changing_anything(): void
    {
        $rooms = collect(app(TemporaryPasswordService::class)->overview(self::$testTenant))->keyBy('key');

        $this->assertSame(['key' => (string) $this->classId, 'room' => 'Klasse 7c', 'new' => 1, 'existing' => 0], $rooms[(string) $this->classId]);
        $this->assertSame(2, $rooms[(string) $this->mainId]['new']);

        self::$testTenant->run(function () {
            $this->assertEmpty(LegacyUser::find($this->studentId)->temp_pw);
        });
    }

    public function test_issuing_for_one_room_leaves_the_other_users_alone(): void
    {
        $result = app(TemporaryPasswordService::class)->issue(self::$testTenant, (string) $this->classId);

        $this->assertCount(1, $result['sheets']);
        $this->assertSame('Klasse 7c', $result['sheets'][0]['room']);
        $this->assertSame([self::PREFIX.'student'], array_column($result['sheets'][0]['rows'], 'username'));
        $this->assertSame(1, $result['new']);

        self::$testTenant->run(function () {
            $this->assertNotEmpty(LegacyUser::find($this->studentId)->temp_pw);
            $this->assertEmpty(LegacyUser::find($this->mainOnlyId)->temp_pw);
        });
    }

    public function test_the_room_sheets_agree_on_a_users_password(): void
    {
        $service = app(TemporaryPasswordService::class);

        $class = $service->issue(self::$testTenant, (string) $this->classId)['sheets'][0];
        $main = $service->issue(self::$testTenant, (string) $this->mainId)['sheets'][0];

        $inMain = collect($main['rows'])->firstWhere('username', self::PREFIX.'student');
        $this->assertSame($class['rows'][0]['password'], $inMain['password']);
    }

    public function test_users_in_no_room_have_a_sheet_of_their_own(): void
    {
        $this->seedUser('roomless', 'Lars Ohne');

        $result = app(TemporaryPasswordService::class)->issue(self::$testTenant, TemporaryPasswordService::NO_ROOM_KEY);

        $this->assertSame(TemporaryPasswordService::NO_ROOM_LABEL, $result['sheets'][0]['room']);
        $usernames = array_column($result['sheets'][0]['rows'], 'username');
        $this->assertContains(self::PREFIX.'roomless', $usernames);
        $this->assertNotContains(self::PREFIX.'student', $usernames);
    }

    public function test_the_summary_tells_new_passwords_from_kept_ones(): void
    {
        $service = app(TemporaryPasswordService::class);
        $before = $service->summary(self::$testTenant);

        $service->issue(self::$testTenant);
        $after = $service->summary(self::$testTenant);

        $this->assertGreaterThanOrEqual(2, $before['new']);
        $this->assertSame(0, $after['new']);
        $this->assertSame($before['new'] + $before['existing'], $after['existing']);
    }

    public function test_the_pdf_renders_cards_with_umlauts(): void
    {
        $rows = array_map(fn (int $i): array => [
            'user_id' => $i,
            'name' => 'Jörg Müller <b>',
            'username' => 'joerg'.$i,
            'password' => 'aB3defgh',
            'classes' => 'Klasse 7c',
        ], range(1, 9));

        $pdf = app(TemporaryPasswordPdf::class)->render(self::$testTenant, [
            'key' => '1',
            'room_id' => 1,
            'room' => 'Klasse 7c',
            'is_class' => true,
            'rows' => $rows,
        ]);

        $this->assertStringStartsWith('%PDF-', $pdf);
        // Eight cards to a page.
        $this->assertSame(2, preg_match_all('#/Type\s*/Page\b#', $pdf));
    }

    public function test_a_room_export_is_a_pdf_named_after_the_room(): void
    {
        $download = app(TemporaryPasswordExport::class)->room(self::$testTenant, (string) $this->classId);

        $this->assertSame("klasse-7c-{$this->classId}.pdf", $download['filename']);
        $this->assertSame('application/pdf', $download['mime']);
        $this->assertStringStartsWith('%PDF-', $download['content']);
        $this->assertSame(1, $download['new']);
    }

    public function test_a_room_with_nobody_to_list_exports_nothing(): void
    {
        $empty = $this->seedRoom('Leer', type: 0);

        $this->assertNull(app(TemporaryPasswordExport::class)->room(self::$testTenant, (string) $empty));
    }

    public function test_the_zip_holds_one_pdf_per_room(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ext-zip is not installed.');
        }

        $download = app(TemporaryPasswordExport::class)->all(self::$testTenant);

        $this->assertSame('application/zip', $download['mime']);
        $this->assertStringEndsWith('.zip', $download['filename']);

        $path = (string) tempnam(sys_get_temp_dir(), 'ziptest');
        file_put_contents($path, $download['content']);
        $zip = new \ZipArchive;
        $zip->open($path);
        $names = array_map(fn (int $i): string => (string) $zip->getNameIndex($i), range(0, $zip->numFiles - 1));
        $pdf = (string) $zip->getFromName("klasse-7c-{$this->classId}.pdf");
        $zip->close();
        unlink($path);

        $this->assertContains("klasse-7c-{$this->classId}.pdf", $names);
        $this->assertContains("aula-{$this->mainId}.pdf", $names);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_the_page_is_linked_from_the_tenant_table_and_edit_page(): void
    {
        foreach (['Resources/TenantResource.php', 'Resources/TenantResource/Pages/EditTenant.php'] as $file) {
            $this->assertStringContainsString(
                'TemporaryPasswordsAction::make()',
                (string) file_get_contents(app_path('Filament/'.$file)),
            );
        }
    }

    private function seedRoom(string $name, int $type): int
    {
        return (int) self::$testTenant->run(fn () => DB::table('au_rooms')->insertGetId([
            'room_name' => $name,
            'status' => 1,
            'hash_id' => self::PREFIX.md5($name),
            'type' => $type,
        ]));
    }

    private function seedUser(string $suffix, string $realname): int
    {
        return (int) self::$testTenant->run(function () use ($suffix, $realname) {
            $user = new LegacyUser;
            $user->username = self::PREFIX.$suffix;
            $user->realname = $realname;
            $user->displayname = $realname;
            $user->email = $suffix.'@tmppdf.example';
            $user->status = UserStatus::Active;
            $user->userlevel = UserLevel::User;
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
            DB::table('au_users_basedata')->whereIn('id', $userIds)->delete();
            DB::table('au_rooms')->whereIn('id', $roomIds)->delete();
        });
    }
}
