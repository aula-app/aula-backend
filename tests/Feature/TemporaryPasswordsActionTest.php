<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Filament\Actions\TemporaryPasswordsAction;
use App\Models\LegacyUser;
use App\Services\TemporaryPasswordPdf;
use App\Services\TemporaryPasswordService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * Covers the manager panel's "Temporary passwords" action: which sheets go
 * into the PDF, and that running it downloads one.
 */
class TemporaryPasswordsActionTest extends TestCase
{
    use CreatesTestTenant;

    private const PREFIX = 'tmppdf.';

    private int $classId;

    private int $mainId;

    private int $studentId;

    private int $roomlessId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        $this->clean();

        $this->classId = $this->seedRoom('Klasse 7c', type: 0);
        $this->mainId = $this->seedRoom('Aula', type: 1);
        $this->studentId = $this->seedUser('student', 'Jörg Müller');
        $this->roomlessId = $this->seedUser('mainonly', 'Käthe Allein');

        $this->enrol($this->classId, $this->studentId);
        $this->enrol($this->mainId, $this->studentId);
        $this->enrol($this->mainId, $this->roomlessId);
    }

    protected function tearDown(): void
    {
        $this->clean();
        parent::tearDown();
    }

    public function test_the_handout_has_a_sheet_per_class_and_one_for_users_without_a_class(): void
    {
        $service = app(TemporaryPasswordService::class);

        $sheets = collect($service->handoutSheets($service->issue(self::$testTenant)['sheets']))->keyBy('room');

        $this->assertFalse($sheets->has('Aula'), 'The main room repeats the class sheets.');
        $this->assertSame([self::PREFIX.'student'], array_column($sheets['Klasse 7c']['rows'], 'username'));
        $this->assertContains(
            self::PREFIX.'mainonly',
            array_column($sheets[TemporaryPasswordService::NO_CLASS_LABEL]['rows'], 'username'),
        );
        $this->assertNotContains(
            self::PREFIX.'student',
            array_column($sheets[TemporaryPasswordService::NO_CLASS_LABEL]['rows'], 'username'),
        );
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

    public function test_the_pdf_renders_names_with_umlauts(): void
    {
        $pdf = app(TemporaryPasswordPdf::class)->render(self::$testTenant, [[
            'room_id' => 1,
            'room' => 'Klasse 7c',
            'is_class' => true,
            'rows' => [[
                'user_id' => 1,
                'name' => 'Jörg Müller <b>',
                'username' => 'joerg',
                'password' => 'aB3defgh',
                'classes' => 'Klasse 7c',
            ]],
        ]]);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_running_the_action_sets_passwords_and_downloads_a_pdf(): void
    {
        $response = TemporaryPasswordsAction::run(self::$testTenant);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('temp-passwords-test001-', (string) $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $this->assertStringStartsWith('%PDF-', (string) ob_get_clean());

        self::$testTenant->run(function () {
            $this->assertNotEmpty(LegacyUser::find($this->studentId)->temp_pw);
        });
    }

    public function test_the_confirmation_text_states_the_counts(): void
    {
        $summary = app(TemporaryPasswordService::class)->summary(self::$testTenant);

        $this->assertStringStartsWith(
            "{$summary['new']} active users have no password of their own",
            TemporaryPasswordsAction::describe(self::$testTenant),
        );
    }

    public function test_the_action_is_offered_on_the_tenant_table_and_edit_page(): void
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
