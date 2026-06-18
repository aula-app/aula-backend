<?php

namespace Tests\Unit;

use App\Models\LegacyUser;
use App\Services\SsoUserService;
use Mockery;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class SsoUserServiceTest extends TestCase
{
    use CreatesTestTenant;

    private SsoUserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        $this->service = new SsoUserService();
    }

    protected function tearDown(): void
    {
        self::$testTenant->run(fn () => LegacyUser::where('email', 'like', 'unit_%@sso.test')->delete());
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================
    // findBySub / findByEmail
    // =========================================================

    public function test_find_by_sub_returns_null_when_no_match(): void
    {
        $result = self::$testTenant->run(
            fn () => $this->service->findBySub('sub-nobody')
        );

        $this->assertNull($result);
    }

    public function test_find_by_sub_matches_existing_user(): void
    {
        $user = self::$testTenant->run(fn () => $this->makeUser('unit_sub@sso.test', 'sub-match-001'));

        $result = self::$testTenant->run(
            fn () => $this->service->findBySub('sub-match-001')
        );

        $this->assertNotNull($result);
        $this->assertEquals($user->id, $result->id);
    }

    public function test_find_by_email_returns_null_when_no_match(): void
    {
        $result = self::$testTenant->run(
            fn () => $this->service->findByEmail('unit_nobody@sso.test')
        );

        $this->assertNull($result);
    }

    public function test_find_by_email_matches_existing_user(): void
    {
        $user = self::$testTenant->run(fn () => $this->makeUser('unit_email@sso.test', null));

        $result = self::$testTenant->run(
            fn () => $this->service->findByEmail('unit_email@sso.test')
        );

        $this->assertNotNull($result);
        $this->assertEquals($user->id, $result->id);
    }

    public function test_find_by_email_returns_null_for_null_or_empty(): void
    {
        self::$testTenant->run(function () {
            $this->assertNull($this->service->findByEmail(null));
            $this->assertNull($this->service->findByEmail(''));
        });
    }

    // =========================================================
    // provisionUser
    // =========================================================

    public function test_provision_user_creates_user_with_correct_fields(): void
    {
        $socialiteUser = $this->makeSocialiteUser('sub-prov-001', 'unit_prov@sso.test', 'Test User', 'testuser');

        $user = self::$testTenant->run(fn () => $this->service->provisionUser($socialiteUser));

        $this->assertEquals('unit_prov@sso.test', $user->email);
        $this->assertEquals('sub-prov-001', $user->sso_sub);
        $this->assertEquals('testuser', $user->username);
        $this->assertEquals('Test User', $user->displayname);
        $this->assertEquals(20, $user->userlevel);
        $this->assertEquals(1, $user->status);
    }

    public function test_provision_user_falls_back_to_email_when_nickname_is_null(): void
    {
        $socialiteUser = $this->makeSocialiteUser('sub-prov-002', 'unit_nonick@sso.test', 'No Nick', null);

        $user = self::$testTenant->run(fn () => $this->service->provisionUser($socialiteUser));

        $this->assertEquals('unit_nonick@sso.test', $user->username);
    }

    // =========================================================
    // addToStandardRoom
    // =========================================================

    // @FIXME: we shouldn't have conditional tests - ensure std. room exists for this test case
    public function test_add_to_standard_room_inserts_membership_when_standard_room_exists(): void
    {
        self::$testTenant->run(function () {
            $user = $this->makeUser('unit_room@sso.test', 'sub-room');

            // Ensure there is at least one standard room (type=1)
            $standardRoom = \Illuminate\Support\Facades\DB::table('au_rooms')->where('type', 1)->first(['id', 'hash_id']);

            if ($standardRoom === null) {
                $this->markTestSkipped('No standard room (type=1) in test tenant.');
            }

            $this->service->addToStandardRoom($user);

            $count = \Illuminate\Support\Facades\DB::table('au_rel_rooms_users')
                ->where('user_id', $user->id)
                ->where('room_id', $standardRoom->id)
                ->count();

            $this->assertEquals(1, $count);

            // Cleanup
            \Illuminate\Support\Facades\DB::table('au_rel_rooms_users')->where('user_id', $user->id)->delete();
        });
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function makeUser(string $email, ?string $sub): LegacyUser
    {
        $user              = new LegacyUser();
        $user->email       = $email;
        $user->sso_sub     = $sub;
        $user->status      = LegacyUser::STATUS_ACTIVE;
        $user->username    = $email;
        $user->hash_id     = md5($email . microtime(true));
        $user->userlevel   = 20;
        $user->roles       = json_encode([]);
        $user->refresh_token = false;
        $user->save();

        return $user;
    }

    private function makeSocialiteUser(string $sub, string $email, string $name, ?string $nickname): mixed
    {
        $mock = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $mock->shouldReceive('getId')->andReturn($sub);
        $mock->shouldReceive('getEmail')->andReturn($email);
        $mock->shouldReceive('getName')->andReturn($name);
        $mock->shouldReceive('getNickname')->andReturn($nickname);

        return $mock;
    }
}
