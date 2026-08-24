<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\LegacyUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Depends;
use DateTimeImmutable;

class CrudUserTest extends TestCase
{
    use CreatesTestTenant;
    use DatabaseTransactions;

    private const array NEW_USER_DATA = [
        'displayName' => 'Firstnamé',
        'userName' => 'aula_testuser',
        'realName' => 'Firstnamé Lastname',
        'status' => UserStatus::Active->value,
    ];

    private const array USER_DATA_UPDATE = [
        'userLevel' => UserLevel::Guest->value,
        'email' => 'e2e_distinct_changed@aula.de',
        'aboutMe' => 'About me!',
    ];

    private const array TENANT_HEADERS = ['aula-instance-code' => 'TEST001'];

    private LegacyUser $adminUser;
    private string $adminJwt;
    private array $adminHeaders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();

        $this->adminUser = $this->createDistinctUser(UserLevel::Admin, UserStatus::Active);
        Passport::actingAs($this->adminUser);

        $this->withHeaders([ ...self::TENANT_HEADERS ]);
    }

    public static function tearDownAfterClass(): void
    {
        self::$testTenant->run(function () {
            LegacyUser::whereLike('email', 'e2e_distinct_%@aula.de')->delete();
        });
    }

    private function createDistinctUser(UserLevel $userLevel, UserStatus $userStatus): LegacyUser
    {
        return self::$testTenant->run(function () use ($userLevel, $userStatus) {
            $email = "e2e_distinct_{$userLevel->name}_" . bin2hex(random_bytes(8)) . '@aula.de';
            $user = new LegacyUser();
            $user->email         = $email;
            $user->displayname   = 'Distinct';
            $user->realname      = 'Distinct User';
            $user->about_me      = 'About me.';
            $user->sso_sub       = null;
            $user->status        = $userStatus;
            $user->username      = $email;
            $user->hash_id       = md5($email);
            $user->userlevel     = $userLevel;
            $user->roles         = json_encode([]);
            $user->refresh_token = false;
            $user->save();
            return $user;
        });
    }

    /**
     * A full PUT body echoing the user's current state, so individual tests can
     * override just the field under test.
     */
    private function putBodyFor(LegacyUser $user): array
    {
        return [
            'displayName' => $user->displayname,
            'userName' => $user->username,
            'realName' => $user->realname,
            'status' => $user->status->value,
            'email' => $user->email,
            'userLevel' => $user->userlevel->value,
            'aboutMe' => $user->about_me,
        ];
    }

    private function jwtForUser(LegacyUser $user): string
    {
        Passport::actingAs($user);
        return '';
        /* return self::$testTenant->run( */
        /*     fn () => app(\App\Services\LegacyJwtService::class)->generateToken($user) */
        /* ) ?? ''; */
    }

    public function test_authz_nonadmin()
    {
        // Note: we can't sanity check by get'ing as admin here first,
        // somehow the first getJson's headers infect all that follow
        // like: only one Bearer per test (function body)...
        //   $res = $this->getJson(
        //     '/api/v2/users',
        //     $this->adminHeaders
        //   )->assertOk();
        $nonAdminUser = $this->createDistinctUser(UserLevel::Moderator, UserStatus::Active);
        $this->jwtForUser($nonAdminUser);
        $this->getJson(
            '/api/v2/users',
        )
            ->assertForbidden();
        $this->postJson(
            '/api/v2/users',
            self::NEW_USER_DATA,
        )
            ->assertForbidden();
    }

    public function test_authz_early_validation()
    {
        $nonAdminUser = $this->createDistinctUser(UserLevel::Moderator, UserStatus::Active);
        $this->jwtForUser($nonAdminUser);
        $this->postJson(
            '/api/v2/users'
        )
            // *not* assertForbidden, because validation (laravel-data) happens *before* Gate
            ->assertUnprocessable();
        // same for admin
        $this->postJson(
            '/api/v2/users',
            [/* empty body! */],
        )
            ->assertUnprocessable();
    }

    public function test_authz_show_self_and_not_other()
    {
        $user = $this->createDistinctUser(UserLevel::Moderator, UserStatus::Active);
        $otherUser = $this->createDistinctUser(UserLevel::User, UserStatus::Active);
        $this->assertNotEquals($user->hash_id, $otherUser->hash_id);
        $jwt = $this->jwtForUser($user);
        $this->getJson(
            "/api/v2/users/{$user->hash_id}",
        )
            ->assertOk();
        $this->getJson(
            "/api/v2/users/{$otherUser->hash_id}",
        )
            ->assertForbidden();
    }

    public function test_authz_self_update_cannot_escalate_userlevel()
    {
        $user = $this->createDistinctUser(UserLevel::User, UserStatus::Active);
        $this->jwtForUser($user);

        $this->putJson(
            "/api/v2/users/{$user->hash_id}",
            [...$this->putBodyFor($user), 'userLevel' => UserLevel::Admin->value],
        )
            ->assertForbidden();

        $this->assertSame(
            UserLevel::User,
            self::$testTenant->run(fn () => LegacyUser::where('hash_id', $user->hash_id)->sole()->userlevel)
        );
    }

    public function test_authz_self_update_cannot_change_status()
    {
        $user = $this->createDistinctUser(UserLevel::User, UserStatus::Active);
        $this->jwtForUser($user);

        $this->putJson(
            "/api/v2/users/{$user->hash_id}",
            [...$this->putBodyFor($user), 'status' => UserStatus::Suspended->value],
        )
            ->assertForbidden();

        $this->assertSame(
            UserStatus::Active,
            self::$testTenant->run(fn () => LegacyUser::where('hash_id', $user->hash_id)->sole()->status)
        );
    }

    public function test_authz_self_update_allows_own_profile_fields()
    {
        $user = $this->createDistinctUser(UserLevel::User, UserStatus::Active);
        $this->jwtForUser($user);

        $this->putJson(
            "/api/v2/users/{$user->hash_id}",
            [...$this->putBodyFor($user), 'realName' => 'Renamed Self']
        )
            ->assertOk()
            ->assertJson(['realName' => 'Renamed Self', 'userLevel' => UserLevel::User->value]);

        $this->assertSame(
            'Renamed Self',
            self::$testTenant->run(fn () => LegacyUser::where('hash_id', $user->hash_id)->sole()->realname)
        );
    }

    public function test_admin_update_may_change_userlevel_and_status()
    {
        $user = $this->createDistinctUser(UserLevel::User, UserStatus::Active);

        // setUp() installs admin credentials as the default headers
        $this->putJson("/api/v2/users/{$user->hash_id}", [
            ...$this->putBodyFor($user),
            'userLevel' => UserLevel::Moderator->value,
            'status' => UserStatus::Suspended->value,
        ])
            ->assertOk()
            ->assertJson([
                'userLevel' => UserLevel::Moderator->value,
                'status' => UserStatus::Suspended->value,
            ]);
    }

    // Passport will not create access tokens for wrong credentials, inactive users, etc.
    // the scope of this FeatureTest file is CRUD Users, rather than details of Passport authN
    // Therefore, we only have one test to check that missing access token will disallow GET /users
    public function test_authz_unauthorized()
    {
        // Necessary to clean up the cached $user from \Illuminate\Auth\RequestGuard
        $this->refreshApplication();
        parent::setUp();
        $this->withHeaders([ ...self::TENANT_HEADERS ]);

        $this->getJson(
            '/api/v2/users',
            [ 'Authorization' => null ]
        )
            ->assertUnauthorized();
    }

    // This test doesn't make too much sense, because it's testing whether inactive users
    // can access GET /users. The real question is whether inactive users would have any
    // active (unrevoked) Access Tokens. This test uses Passport::actingAs($user) to
    // check the authZ for GET /users, but that's only a fallback check - our first line
    // of defense is not issuing (and revoking) tokens to non-Active users.
    public function test_authz_not_active_statuses()
    {
        foreach ([UserStatus::Inactive, UserStatus::Suspended, UserStatus::Archived] as $userStatus) {
            /* // Necessary to clean up the cached $user from \Illuminate\Auth\RequestGuard */
            /* $this->refreshApplication(); */
            /* parent::setUp(); */
            $inActiveUser = $this->createDistinctUser(UserLevel::Admin, $userStatus);
            $this->jwtForUser($inActiveUser);
            $this->getJson('/api/v2/users')
                ->assertForbidden();
        }
    }

    public function test_create()
    {
        $result = $this->post(
            '/api/v2/users',
            self::NEW_USER_DATA,
        )
            ->assertCreated()
            ->assertJsonMissingPath('id')
            ->assertJson(self::NEW_USER_DATA);
        $newUserDecoded = $result->decodeResponseJson();
        $this->assertIsString($newUserDecoded['createdAt']);
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $newUserDecoded['createdAt']));
        $newUserPublicId = $newUserDecoded['publicId'];
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $newUserPublicId);
        return $newUserPublicId;
    }

    public function test_create_optional()
    {
        $result = $this->post(
            '/api/v2/users',
            [...self::NEW_USER_DATA, ...self::USER_DATA_UPDATE],
        )
            ->assertCreated()
            ->assertJson(self::USER_DATA_UPDATE);
        $newUserPublicId = $result->decodeResponseJson()['publicId'];
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $newUserPublicId);
        return $newUserPublicId;
    }

    // The tests in this file are brittle in terms of order --
    // they need to be run in order "of appearance",
    // the order Depends creates is not sufficient to allow
    // specific tests solitarily, out of order.
    // TODO: make them more isolated (self::cleanUp is not enough)
    #[Depends('test_create')]
    public function test_show($newUserPublicId)
    {
        $this->getJson('/api/v2/users/'.$newUserPublicId)
            ->assertOk()
            ->assertJsonMissingPath('id')
            ->assertJson(self::NEW_USER_DATA);
    }

    #[Depends('test_create_optional')]
    public function test_show_optional($newUserPublicId)
    {
        $this->getJson('/api/v2/users/'.$newUserPublicId)
            ->assertOk()
            ->assertJson([...self::NEW_USER_DATA, ...self::USER_DATA_UPDATE]);
    }

    #[Depends('test_create')]
    #[Depends('test_create_optional')]
    public function test_index($newUserPublicId1, $newUserPublicId2)
    {
        $allUsers = $this->getJson('/api/v2/users')
            ->assertOk()->json();

        $allUserPublicIds = array_column($allUsers, 'publicId');
        $this->assertContains($newUserPublicId1, $allUserPublicIds);
        $this->assertContains($newUserPublicId2, $allUserPublicIds);
    }

    #[Depends('test_create')]
    public function test_update($newUserPublicId)
    {
        $changedUserData = [
            ...self::NEW_USER_DATA,
            ...self::USER_DATA_UPDATE,
            ...['realName' => 'Changed Name'],
        ];

        $result = $this->putJson(
            '/api/v2/users/'.$newUserPublicId,
            $changedUserData,
        )
            ->assertOk()
            ->assertJsonMissingPath('id')
            ->assertJson($changedUserData);
        $updatedUserDecoded = $result->decodeResponseJson();
        $this->assertIsString($updatedUserDecoded['updatedAt']);
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $updatedUserDecoded['updatedAt']));
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $updatedUserDecoded['createdAt']));
        $this->assertGreaterThanOrEqual($updatedUserDecoded['createdAt'], $updatedUserDecoded['updatedAt']);
    }

    #[Depends('test_create')]
    public function test_update_required($newUserPublicId)
    {
        $result = $this->putJson(
            '/api/v2/users/'.$newUserPublicId,
            self::NEW_USER_DATA
        );
        $result
            ->assertInvalid(['email', 'userLevel', 'aboutMe'])
            ->assertUnprocessable();
    }

    #[Depends('test_create')]
    public function test_update_validation($newUserPublicId)
    {
        $changedUserData = [
            ...self::NEW_USER_DATA,
            ...self::USER_DATA_UPDATE,
            ...['email' => 'bad@mail_cannothaveunderscores.com'],
            ...['userLevel' => 1000],
        ];
        $this->putJson(
            '/api/v2/users/'.$newUserPublicId,
            $changedUserData,
        )
            ->assertInvalid(['email', 'userLevel'])
            ->assertUnprocessable();
    }

    #[Depends('test_create')]
    public function test_authz_delete_admin_only($newUserPublicId1)
    {
        $nonAdminUser = $this->createDistinctUser(UserLevel::Moderator, UserStatus::Active);
        $nonAdminJwt = $this->jwtForUser($nonAdminUser);
        // non admin tries do delete other
        $this->deleteJson(
            '/api/v2/users/'.$newUserPublicId1,
            []
        )
            ->assertForbidden();
        // non admin tries to delete self
        $this->deleteJson(
            '/api/v2/users/'.$nonAdminUser->hash_id,
            []
        )
            ->assertForbidden();
    }

    #[Depends('test_create')]
    #[Depends('test_create_optional')]
    public function test_delete($newUserPublicId1, $newUserPublicId2)
    {
        $this->deleteJson('/api/v2/users/'.$newUserPublicId1, [])
            ->assertOk();
        $this->deleteJson('/api/v2/users/'.$newUserPublicId2, [])
            ->assertOk();
    }

    public function test_create_validation()
    {
        foreach ([
            ['createdAt' => '2001-01-23T12:34:56Z'],
            ['createdAt' => 'nondate'],
            ['createdAt' => ''],
            // created, last_update, publicId musst be *missing* from request
            ['publicId' => ''],
            ['publicId' => null],
            ['updatedAt' => ''],
            ['userName' => null],
            ['userName' => ''],
            ['displayName' => str_repeat('A', 500)],
            ['email' => 'bad@mail_cannothaveunderscores.com'],
            ['userLevel' => '1000'],
            ['userLevel' => 1000],
            ['status' => 5],
            ['status' => '5'],
        ] as $kv) {
            $this->postJson('/api/v2/users', [...self::NEW_USER_DATA, ...$kv])
                ->assertInvalid(array_key_first($kv))
                ->assertUnprocessable();
        }
    }

    #[Depends('test_create')]
    public function test_patch_disallowed($newUserPublicId)
    {
        // need an existing user, PATCH to /api/v2/users/foo will 404 before 405ing
        $this->patchJson('/api/v2/users/'.$newUserPublicId, [])
            ->assertMethodNotAllowed();
    }

    public function test_bad_show()
    {
        // unfortunately we can't easily distinguish between "invalid route param" and "valid, but not found"
        // (= whether the ShowUserUseCase even executes)
        $this->getJson('/api/v2/users/1', [])
            ->assertNotFound();
        $this->getJson('/api/v2/users/foo', [])
            ->assertNotFound();
        $this->getJson('/api/v2/users/0123456789abcdef0123456789abcdef', [])
            ->assertNotFound();
    }

    public function test_bad_deletes()
    {
        $this->deleteJson('/api/v2/users/1000000', [])
            ->assertNotFound();
        $this->deleteJson('/api/v2/users/foo', [])
            ->assertNotFound();
    }
}
