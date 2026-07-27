<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\LegacyUser;
use App\Models\LegacyRoom;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Depends;
use DateTimeImmutable;

class CrudUserTest extends TestCase
{
    use CreatesTestTenant;

    private const array NEW_USER_DATA = [
        'displayname' => 'Firstnamé',
        'username' => 'aula_testuser',
        'realname' => 'Firstnamé Lastname',
        'status' => UserStatus::Active->value,
    ];

    private const array USER_DATA_UPDATE = [
        'userlevel' => UserLevel::Guest->value,
        'email' => 'featuretest@aula.de',
        'about_me' => 'About me!',
    ];

    private const array NEW_ROOM_DATA = [
        'room_name' => 'Testroom',
        'description_public' => 'Public test description',
        'description_internal' => 'Internal test description',
        'phase_duration_1' => 14,
        'phase_duration_3' => 14,
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

    private function createUserIfNotExists(UserLevel $userLevel = UserLevel::Guest, UserStatus $userStatus = UserStatus::Active): LegacyUser
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
            $user->displayname   = self::NEW_USER_DATA['displayname'];
            $user->realname      = self::NEW_USER_DATA['realname'];
            $user->about_me      = self::USER_DATA_UPDATE['about_me'];
            $user->sso_sub       = null;
            $user->status        = $userStatus;
            $user->username      = self::NEW_USER_DATA['username'];
            $user->hash_id       = md5($email . microtime(true));
            $user->userlevel     = $userLevel;
            $user->roles         = json_encode([]);
            $user->refresh_token = false;
            $user->save();
            return $user;
        });
    }

    private function createRoomIfNotExists(): LegacyRoom
    {
        return self::$testTenant->run(function () {
            $existingRoom = LegacyRoom::first();
            if ($existingRoom) {
                return $existingRoom;
            }
            $room = new LegacyRoom();
            $room->hash_id = Str::random(32);
            $room->room_name = 'TestRoom';
            /*
            $room->description_public = "...";
            $room->description_internal = "...";
            $room->phase_duration_1 = 14;
            $room->phase_duration_3 = 14;
            */
            $room->save();
            return $room;
        });
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
        $this->assertIsString($newUserDecoded['created_at']);
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $newUserDecoded['created_at']));
        $newUserPublicId = $newUserDecoded['public_id'];
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
        $newUserPublicId = $result->decodeResponseJson()['public_id'];
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

        $allUserPublicIds = array_column($allUsers, 'public_id');
        $this->assertContains($newUserPublicId1, $allUserPublicIds);
        $this->assertContains($newUserPublicId2, $allUserPublicIds);
    }

    #[Depends('test_create')]
    public function test_update($newUserPublicId)
    {
        $changedUserData = [
            ...self::NEW_USER_DATA,
            ...self::USER_DATA_UPDATE,
            ...['realname' => 'Changed Name'],
        ];

        $result = $this->putJson(
            '/api/v2/users/'.$newUserPublicId,
            $changedUserData,
        )
            ->assertOk()
            ->assertJsonMissingPath('id')
            ->assertJson($changedUserData);
        $updatedUserDecoded = $result->decodeResponseJson();
        $this->assertIsString($updatedUserDecoded['updated_at']);
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $updatedUserDecoded['updated_at']));
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $updatedUserDecoded['created_at']));
        $this->assertGreaterThanOrEqual($updatedUserDecoded['created_at'], $updatedUserDecoded['updated_at']);
    }

    #[Depends('test_create')]
    public function test_update_required($newUserPublicId)
    {
        $result = $this->putJson(
            '/api/v2/users/'.$newUserPublicId,
            self::NEW_USER_DATA
        );
        $result
            ->assertInvalid(['email', 'userlevel', 'about_me'])
            ->assertUnprocessable();
    }

    #[Depends('test_create')]
    public function test_update_validation($newUserPublicId)
    {
        $changedUserData = [
            ...self::NEW_USER_DATA,
            ...self::USER_DATA_UPDATE,
            ...['email' => 'bad@mail_huh.com'],
            ...['userlevel' => 1000],
        ];
        $this->putJson(
            '/api/v2/users/'.$newUserPublicId,
            $changedUserData,
        )
            ->assertInvalid(['email', 'userlevel'])
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
            ['created_at' => '2001-01-23T12:34:56Z'],
            ['created_at' => 'nondate'],
            ['created_at' => ''],
            // created, last_update, public_id musst be *missing* from request
            ['public_id' => ''],
            ['public_id' => null],
            ['updated_at' => ''],
            ['username' => null],
            ['username' => ''],
            ['displayname' => str_repeat('A', 500)],
            ['email' => 'bad@mail_huh.com'],
            ['userlevel' => '1000'],
            ['userlevel' => 1000],
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

    public function test_show_gdpr_info()
    {
        $user = $this->createUserIfNotExists();
        $this->getJson('/api/v2/user-gdpr-info/'.$user->hash_id)
            ->assertOk()
            ->assertJsonMissingPath('id')
            ->assertJson([
                'user' => self::NEW_USER_DATA,
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

    public function test_create_room()
    {
        $result = $this->postJson(
            '/api/v2/rooms',
            self::NEW_ROOM_DATA,
        )
            ->assertCreated()
            ->assertJsonMissingPath('id')
            ->assertJson(self::NEW_ROOM_DATA);
        $newRoomDecoded = $result->decodeResponseJson();
        $this->assertIsString($newRoomDecoded['created_at']);
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $newRoomDecoded['created_at']));
        $newRoomPublicId = $newRoomDecoded['public_id'];
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $newRoomPublicId);
        return $newRoomPublicId;
    }

    // TODO create seperate functions
    public function test_roomuser()
    {
        $user = $this->createUserIfNotExists();
        $room = $this->createRoomIfNotExists();
        $newUserPublicId = $user->hash_id;
        $newRoomPublicId = $room->hash_id;
        $expect = [
            "room_public_id" => $newRoomPublicId,
            "user_public_id" => $newUserPublicId,
            "room_user_level" => 10,
        ];
        $this->putJson(
            "/api/v2/rooms/{$newRoomPublicId}/users/{$newUserPublicId}",
            ["room_user_level" => 10]
        )
            ->assertOk()
            ->assertExactJson($expect);
        $this->putJson(
            "/api/v2/rooms/{$newRoomPublicId}/users/{$newUserPublicId}",
            ["room_user_level" => 101]
        )
            ->assertUnprocessable();
        $this->getJson("/api/v2/rooms/{$newRoomPublicId}/users/{$newUserPublicId}")
            ->assertOk()
            ->assertExactJson($expect);
        // TODO: put same again, test that they do not add up
        $this->getJson("/api/v2/rooms/{$newRoomPublicId}/users")
            ->assertOk()
            ->assertExactJson([$expect]);
        $expect["room_user_level"] = 20;
        $this->putJson(
            "/api/v2/rooms/{$newRoomPublicId}/users/{$newUserPublicId}",
            ["room_user_level" => 20]
        )
            ->assertOk()
            ->assertExactJson($expect);
        $this->deleteJson(
            "/api/v2/rooms/{$newRoomPublicId}/users/{$newUserPublicId}",
        )
            ->assertOk();
        $this->getJson("/api/v2/rooms/{$newRoomPublicId}/users")
            ->assertOk()
            ->assertExactJson([]);
    }
}
