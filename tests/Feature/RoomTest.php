<?php

// TODO: this is WIP, mostly testing room/membership

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Depends;
use Tests\TestCase;
use Tests\Concerns\CreatesTestTenant;
use App\Models\LegacyUser;
use Laravel\Passport\Passport;

class RoomTest extends TestCase
{
    use CreatesTestTenant;

    private const array TENANT_HEADERS = ['aula-instance-code' => 'TEST001'];

    private const array NEW_ROOM_DATA = [
        'name' => 'Testroom',
        'status' => 1,
        'descriptionPublic' => 'Public test description',
        'descriptionInternal' => 'Internal test description',
        'phaseDuration1' => 14,
        'phaseDuration3' => 14,
    ];

    private const array UPDATE_ROOM_DATA = [
        'name' => 'Testroom renamed',
        'status' => 1,
        'descriptionPublic' => 'Public test description changed',
        'descriptionInternal' => 'Internal test description changed',
        'phaseDuration1' => 15,
        'phaseDuration3' => 15,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();

        $adminUser = $this->createDistinctUser(UserLevel::Admin, UserStatus::Active);
        Passport::actingAs($adminUser);

        $this->withHeaders([
            ...self::TENANT_HEADERS
        ]);
    }

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
    private function jwtForUser(LegacyUser $user): string
    {
        Passport::actingAs($user);
        return '';
        /*
        return self::$testTenant->run(
            fn () => app(\App\Services\LegacyJwtService::class)->generateToken($user)
        ) ?? '';
         */
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
        $this->assertIsString($newRoomDecoded['createdAt']);
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $newRoomDecoded['createdAt']));
        $newRoomPublicId = $newRoomDecoded['publicId'];
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $newRoomPublicId);
        return $newRoomPublicId;
    }

    public function test_create_room_required()
    {
        $this->postJson(
            '/api/v2/rooms',
            []
        )
            ->assertInvalid(['name', 'status', 'descriptionPublic', 'descriptionInternal', 'phaseDuration1', 'phaseDuration3'])
            ->assertUnprocessable();
    }

    public function test_create_room_validation()
    {
        foreach ([
            ['createdAt' => '2001-01-23T12:34:56Z'],
            ['createdAt' => 'nondate'],
            ['createdAt' => ''],
            ['publicId' => ''],
            ['publicId' => null],
            ['updatedAt' => ''],
            ['name' => null],
            ['name' => ''],
            ['name' => str_repeat('A', 2000)],
            ['status' => null],
            ['status' => ''],
            ['status' => 'a'],
            ['descriptionPublic' => ''],
            ['descriptionPublic' => null],
            ['phaseDuration1' => ''],
            ['phaseDuration1' => -1],
            ['phaseDuration1' => 0],
            ['phaseDuration3' => ''],
        ] as $kv) {
            $this->postJson('/api/v2/rooms', [...self::NEW_ROOM_DATA, ...$kv])
                ->assertInvalid(array_key_first($kv))
                ->assertUnprocessable();
        }
    }

    #[Depends('test_create_room')]
    public function test_update_room($newRoomPublicId)
    {
        $result = $this->putJson(
            "/api/v2/rooms/$newRoomPublicId",
            self::UPDATE_ROOM_DATA,
        )
            ->assertOk()
            ->assertJsonMissingPath('id')
            ->assertJson(self::UPDATE_ROOM_DATA);
        $updatedRoom = $result->decodeResponseJson();
        $this->assertIsString($updatedRoom['updatedAt']);
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $updatedRoom['updatedAt']));
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $updatedRoom['createdAt']));
        $this->assertGreaterThanOrEqual($updatedRoom['createdAt'], $updatedRoom['updatedAt']);
        return $newRoomPublicId;
    }

    #[Depends('test_create_room')]
    public function test_update_room_required($newRoomPublicId)
    {
        $this->putJson(
            "/api/v2/rooms/$newRoomPublicId",
            []
        )
            ->assertInvalid(['name', 'status', 'descriptionPublic', 'descriptionInternal', 'phaseDuration1', 'phaseDuration3'])
            ->assertUnprocessable();
    }

    public function test_create_update_room_authz()
    {
        $nonAdminUser = $this->createDistinctUser(UserLevel::Moderator, UserStatus::Active);
        Passport::actingAs($nonAdminUser);
        $this->getJson('/api/v2/rooms')
            ->assertForbidden();
        $this->postJson(
            '/api/v2/rooms',
            self::NEW_ROOM_DATA,
        )->assertForbidden();
    }

    #[Depends('test_create_room')]
    public function test_index_read_delete_room_authz($newRoomPublicId)
    {
        $nonAdminUser = $this->createDistinctUser(UserLevel::Moderator, UserStatus::Active);
        Passport::actingAs($nonAdminUser);
        $this->getJson("/api/v2/rooms")
            ->assertForbidden();
        $this->getJson("/api/v2/rooms/$newRoomPublicId")
            ->assertForbidden();
        $this->deleteJson("/api/v2/rooms/$newRoomPublicId")
            ->assertForbidden();
    }

    #[Depends('test_create_room')]
    public function test_index_room($newRoomPublicId)
    {
        $allRooms = $this->getJson("/api/v2/rooms")
            ->assertOk()
            ->json();
        $allRoomPublicIds = array_column($allRooms, 'publicId');
        $this->assertContains($newRoomPublicId, $allRoomPublicIds);
    }

    #[Depends('test_update_room')]
    public function test_show_room($newRoomPublicId)
    {
        $this->getJson("/api/v2/rooms/$newRoomPublicId")
            ->assertOk()
            ->assertJson(self::UPDATE_ROOM_DATA);
    }

    public function test_bad_show()
    {
        $this->getJson('/api/v2/rooms/1', [])
            ->assertNotFound();
        $this->getJson('/api/v2/rooms/foo', [])
            ->assertNotFound();
        $this->getJson('/api/v2/rooms/0123456789abcdef0123456789abcdef', [])
            ->assertNotFound();
    }

    #[Depends('test_create_room')]
    public function test_patch_disallowed($newRoomPublicId)
    {
        $this->patchJson('/api/v2/rooms/'.$newRoomPublicId, [])
            ->assertMethodNotAllowed();
    }

    #[Depends('test_create_room')]
    public function test_room_membership_batch_validation($newRoomPublicId)
    {
        foreach([
            [["add" => ["nonempty"]], ["add.0"]],
            [["add" => [["nonempty"]]], ["add.0.publicId"]],
            [["add" => [["publicId" => "foo"]]], ["add.0.level"]],
            [["add" => [["publicId" => "foo", "level" => 666]]], ["add.0.level"]],
            [["add" => [["publicId" => "foo", "level" => 10], ["level" => 10]]], ["add.1.publicId"]],
        ] as $kv) {
            $this->patchJson(
                "/api/v2/rooms/{$newRoomPublicId}/membership",
                $kv[0],
            )
                ->assertInvalid($kv[1])
                ->assertUnprocessable();
        }
    }

    #[Depends('test_create_room')]
    public function test_room_membership_batch($newRoomPublicId)
    {
        $user1 = $this->createDistinctUser(UserLevel::User, UserStatus::Active);
        $user2 = $this->createDistinctUser(UserLevel::Moderator, UserStatus::Active);
        $newUserPublicId1 = $user1->hash_id;
        $newUserPublicId2 = $user2->hash_id;
        $this->getJson("/api/v2/rooms/{$newRoomPublicId}/membership")
            ->assertExactJson([]);

        // add user1 and user2 to room
        $this->patchJson(
            "/api/v2/rooms/{$newRoomPublicId}/membership",
            [
                "add" => [
                    ["publicId" => $newUserPublicId1, "level" => 10],
                    ["publicId" => $newUserPublicId2, "level" => 20],
                ]
            ]
        )->assertOk();

        // check if their userlevels and roomlevels are correct
        $this->getJson("/api/v2/rooms/{$newRoomPublicId}/membership")
            ->assertOk()
            ->assertJson(
                [
                    ["publicId" => $newUserPublicId1, "userLevel" => 20, "roomLevel" => 10],
                    ["publicId" => $newUserPublicId2, "userLevel" => 30, "roomLevel" => 20],
                ]
            );

        // set user1's roomlevel to 30
        $this->patchJson(
            "/api/v2/rooms/{$newRoomPublicId}/membership",
            [
                "add" => [
                    ["publicId" => $newUserPublicId1, "level" => 30],
                ]
            ]
        )->assertOk();
        $this->getJson("/api/v2/rooms/{$newRoomPublicId}/membership")
            ->assertJson(
                [["publicId" => $newUserPublicId1, "userLevel" => 20, "roomLevel" => 30]]
            );

        // test transaction; `add` will 404, so `remove`, which runs first, should be rolled back
        $this->patchJson(
            "/api/v2/rooms/{$newRoomPublicId}/membership",
            [
                "add" => [
                    ["publicId" => "not_found", "level" => 10],
                ],
                "remove" => [
                    [$newUserPublicId1]
                ]
            ]
        )->assertNotFound();
        $this->getJson("/api/v2/rooms/{$newRoomPublicId}/membership")
            ->assertOk()
            ->assertJson(
                [
                    ["publicId" => $newUserPublicId1, "userLevel" => 20, "roomLevel" => 30],
                    ["publicId" => $newUserPublicId2, "userLevel" => 30, "roomLevel" => 20],
                ]
            );

        // test remove
        $this->patchJson(
            "/api/v2/rooms/{$newRoomPublicId}/membership",
            ["remove" => [$newUserPublicId1]]
        )->assertOk();
        $this->getJson("/api/v2/rooms/{$newRoomPublicId}/membership")
            ->assertJsonMissing([["publicId" => $newUserPublicId1]])
            ->assertJson([["publicId" => $newUserPublicId2]]);
    }

    #[Depends('test_create_room')]
    public function test_room_membership_batch_404s($newRoomPublicId)
    {
        $this->patchJson(
            "/api/v2/rooms/{$newRoomPublicId}/membership",
            ["add" => [["publicId" => "badid", "level" => 10]]],
        )->assertNotFound();
        $this->patchJson(
            "/api/v2/rooms/{$newRoomPublicId}/membership",
            ["remove" => ["badid"]]
        )->assertNotFound();
    }

}
