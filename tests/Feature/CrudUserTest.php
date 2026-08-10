<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\LegacyUser;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Depends;
use DateTimeImmutable;

class CrudUserTest extends TestCase
{
    use CreatesTestTenant;

    private const array NEW_USER_DATA = [
        'displayName' => 'Firstnamé',
        'userName' => 'aula_testuser',
        'realName' => 'Firstnamé Lastname',
        'status' => UserStatus::Active->value,
    ];

    private const array USER_DATA_UPDATE = [
        'userLevel' => UserLevel::Guest->value,
        'email' => 'featuretest@aula.de',
        'aboutMe' => 'About me!',
    ];

    private const array TENANT_HEADERS = ['aula-instance-code' => 'TEST001'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();

        $adminUser = $this->createUserIfNotExists(UserLevel::TechAdmin, UserStatus::Active);
        $adminJwt = $this->jwtForUser($adminUser);

        $this->withHeaders([
            ...self::TENANT_HEADERS,
            ...['Authorization' => "Bearer {$adminJwt}"],
        ]);
    }

    private function createUserIfNotExists(UserLevel $userLevel, UserStatus $userStatus): LegacyUser
    {
        return self::$testTenant->run(function () use ($userLevel, $userStatus) {
            $existingUser = LegacyUser::where([
                'userlevel' => $userLevel->value,
                'status' => $userStatus->value
            ]);
            if ($existingUser->exists()) {
                return $existingUser->first();
            }
            $user = new LegacyUser();
            $email = "e2e_test_level{$userLevel->value}_status{$userStatus->value}@aula.de";
            $user->email         = $email;
            $user->displayname   = self::NEW_USER_DATA['displayName'];
            $user->realname      = self::NEW_USER_DATA['realName'];
            $user->about_me      = self::USER_DATA_UPDATE['aboutMe'];
            $user->sso_sub       = null;
            $user->status        = $userStatus;
            $user->username      = self::NEW_USER_DATA['userName'];
            $user->hash_id       = md5($email . microtime(true));
            $user->userlevel     = $userLevel;
            $user->roles         = json_encode([]);
            $user->refresh_token = false;
            $user->save();
            return $user;
        });
    }

    /**
     * Unlike createUserIfNotExists(), this always inserts a fresh row, so tests
     * that mutate their subject can't clobber each other's fixtures.
     */
    private function createDistinctUser(UserLevel $userLevel, UserStatus $userStatus): LegacyUser
    {
        return self::$testTenant->run(function () use ($userLevel, $userStatus) {
            $email = 'e2e_distinct_' . bin2hex(random_bytes(8)) . '@aula.de';
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
        return self::$testTenant->run(
            fn () => app(\App\Services\LegacyJwtService::class)->generateToken($user)
        ) ?? '';
    }

    public function test_authz_nonadmin()
    {
        $nonAdminUser = $this->createUserIfNotExists(UserLevel::Moderator, UserStatus::Active);
        $nonAdminJwt = $this->jwtForUser($nonAdminUser);
        $this->getJson(
            '/api/v2/users',
            // override default admin headers
            ['Authorization' => "Bearer {$nonAdminJwt}"]
        )
            ->assertForbidden();
        $this->postJson(
            '/api/v2/users',
            [],
            ['Authorization' => "Bearer {$nonAdminJwt}"]
        )
            // validation happens *before* Gate
            ->assertUnprocessable();
        $this->postJson(
            '/api/v2/users',
            self::NEW_USER_DATA,
            ['Authorization' => "Bearer {$nonAdminJwt}"]
        )
            ->assertForbidden();
    }

    public function test_authz_show_self()
    {
        $user = $this->createUserIfNotExists(UserLevel::Moderator, UserStatus::Active);
        $otherUser = $this->createUserIfNotExists(UserLevel::User, UserStatus::Active);
        $this->assertNotEquals($user->hash_id, $otherUser->hash_id);
        $jwt = $this->jwtForUser($user);
        $this->getJson(
            "/api/v2/users/{$user->hash_id}",
            ['Authorization' => "Bearer {$jwt}"]
        )
            ->assertOk();
        $this->getJson(
            "/api/v2/users/{$otherUser->hash_id}",
            ['Authorization' => "Bearer {$jwt}"]
        )
            ->assertForbidden();
    }

    public function test_authz_self_update_cannot_escalate_userlevel()
    {
        $user = $this->createDistinctUser(UserLevel::User, UserStatus::Active);
        $jwt = $this->jwtForUser($user);

        $this->putJson(
            "/api/v2/users/{$user->hash_id}",
            [...$this->putBodyFor($user), 'userLevel' => UserLevel::TechAdmin->value],
            ['Authorization' => "Bearer {$jwt}"]
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
        $jwt = $this->jwtForUser($user);

        $this->putJson(
            "/api/v2/users/{$user->hash_id}",
            [...$this->putBodyFor($user), 'status' => UserStatus::Suspended->value],
            ['Authorization' => "Bearer {$jwt}"]
        )
            ->assertForbidden();
    }

    public function test_authz_self_update_allows_own_profile_fields()
    {
        $user = $this->createDistinctUser(UserLevel::User, UserStatus::Active);
        $jwt = $this->jwtForUser($user);

        $this->putJson(
            "/api/v2/users/{$user->hash_id}",
            [...$this->putBodyFor($user), 'realName' => 'Renamed Self'],
            ['Authorization' => "Bearer {$jwt}"]
        )
            ->assertOk()
            ->assertJson(['realName' => 'Renamed Self', 'userLevel' => UserLevel::User->value]);
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

    public function test_authz_inactive()
    {
        $inActiveUser = $this->createUserIfNotExists(UserLevel::TechAdmin, UserStatus::Inactive);
        $inActiveJwt = $this->jwtForUser($inActiveUser);
        $this->getJson(
            '/api/v2/users',
            ['Authorization' => "Bearer {$inActiveJwt}"]
        )
            ->assertJson(['error' => 'user_not_active'])
            ->assertUnauthorized();
    }

    public function test_authz_unauthorized()
    {
        $this->getJson(
            '/api/v2/users',
            ['Authorization' => null]
        )
            ->assertJson(['error' => 'Authorization header missing or invalid'])
            ->assertUnauthorized();
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
        $allUsers = $this->getJson('/api/v2/users/')
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
            ...['email' => 'bad@mail_huh.com'],
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
            ['email' => 'bad@mail_huh.com'],
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

    public function test_export_gdpr_info()
    {
        $user = $this->createDistinctUser(UserLevel::User, UserStatus::Active);
        $this->getJson("/api/v2/users/{$user->hash_id}/export")
            ->assertOk()
            ->assertJsonMissingPath('id')
            ->assertJson([
                'user' => [
                    'displayName' => 'Distinct',
                    'realName' => 'Distinct User',
                ],
                // TODO: how to test ideas & comments? create here?
                'userIdeas' => '',
                'userComments' => '',
            ]);
    }

    public function test_authz_show_gdpr_info_self()
    {
        $user = $this->createUserIfNotExists(UserLevel::Moderator, UserStatus::Active);
        $otherUser = $this->createUserIfNotExists(UserLevel::User, UserStatus::Active);
        $this->assertNotEquals($user->hash_id, $otherUser->hash_id);
        $jwt = $this->jwtForUser($user);
        $this->getJson(
            "/api/v2/user-gdpr-info/{$user->hash_id}",
            ['Authorization' => "Bearer {$jwt}"]
        )
            ->assertOk();
        $this->getJson(
            "/api/v2/user-gdpr-info/{$otherUser->hash_id}",
            ['Authorization' => "Bearer {$jwt}"]
        )
            ->assertForbidden();
    }
}
