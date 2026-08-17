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

class RoomTest extends TestCase
{
    use CreatesTestTenant;

    private const array TENANT_HEADERS = ['aula-instance-code' => 'TEST001'];

    private const array NEW_ROOM_DATA = [
        'name' => 'Testroom',
        'descriptionPublic' => 'Public test description',
        'descriptionInternal' => 'Internal test description',
        'phaseDuration1' => 14,
        'phaseDuration3' => 14,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();

        $adminUser = $this->createDistinctUser(UserLevel::TechAdmin, UserStatus::Active);
        $adminJwt = $this->jwtForUser($adminUser);

        $this->withHeaders([
            ...self::TENANT_HEADERS,
            ...['Authorization' => "Bearer {$adminJwt}"],
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
        return self::$testTenant->run(
            fn () => app(\App\Services\LegacyJwtService::class)->generateToken($user)
        ) ?? '';
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

    public function test_create_room_authz()
    {
        $nonAdminUser = $this->createDistinctUser(UserLevel::Moderator, UserStatus::Active);
        $nonAdminJwt = $this->jwtForUser($nonAdminUser);
        $this->getJson(
            '/api/v2/rooms',
            // override default admin headers
            ['Authorization' => "Bearer {$nonAdminJwt}"]
        )
            ->assertForbidden();
    }

    // TODO create seperate functions
    #[Depends('test_create_room')]
    public function test_room_membership_crud($newRoomPublicId)
    {
        $user = $this->createDistinctUser(UserLevel::User, UserStatus::Active);
        $user2 = $this->createDistinctUser(UserLevel::User, UserStatus::Active);
        $newUserPublicId = $user->hash_id;
        $newUserPublicId2 = $user2->hash_id;
        $expect = [
            "roomPublicId" => $newRoomPublicId,
            "userPublicId" => $newUserPublicId,
            "roomUserLevel" => 10,
        ];
        $expect2 = [
            "roomPublicId" => $newRoomPublicId,
            "userPublicId" => $newUserPublicId2,
            "roomUserLevel" => 20,
        ];

        $this->putJson(
            "/api/v2/rooms/{$newRoomPublicId}/members/{$newUserPublicId}",
            ["roomUserLevel" => 10]
        )
            ->assertOk()
            ->assertExactJson($expect);
        $this->putJson(
            "/api/v2/rooms/{$newRoomPublicId}/members/{$newUserPublicId}",
            ["roomUserLevel" => 101]
        )
            ->assertUnprocessable();
        $this->getJson("/api/v2/rooms/{$newRoomPublicId}/members/{$newUserPublicId}")
            ->assertOk()
            ->assertExactJson($expect);
        $this->getJson("/api/v2/rooms/{$newRoomPublicId}/members")
            ->assertOk()
            ->assertExactJson([$expect]);
        $expect["roomUserLevel"] = 20;
        $this->putJson(
            "/api/v2/rooms/{$newRoomPublicId}/members/{$newUserPublicId}",
            ["roomUserLevel" => 20]
        )
            ->assertOk()
            ->assertExactJson($expect);
        // repeat, check that they not append
        $this->putJson(
            "/api/v2/rooms/{$newRoomPublicId}/members/{$newUserPublicId}",
            ["roomUserLevel" => 20]
        )
            ->assertOk()
            ->assertExactJson($expect);
        $this->putJson(
            "/api/v2/rooms/{$newRoomPublicId}/members/{$newUserPublicId2}",
            ["roomUserLevel" => 20]
        )
            ->assertOk()
            ->assertExactJson($expect2);
        $this->getJson("/api/v2/rooms/{$newRoomPublicId}/members")
            ->assertOk()
            ->assertExactJson([$expect, $expect2]);
        $this->deleteJson(
            "/api/v2/rooms/{$newRoomPublicId}/members/{$newUserPublicId}",
        )
            ->assertOk();
        $this->getJson("/api/v2/rooms/{$newRoomPublicId}/members")
            ->assertOk()
            ->assertExactJson([$expect2]);
        $this->deleteJson(
            "/api/v2/rooms/{$newRoomPublicId}/members/{$newUserPublicId2}",
        )
            ->assertOk();
        $this->getJson("/api/v2/rooms/{$newRoomPublicId}/members")
            ->assertOk()
            ->assertExactJson([]);
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
        $this->patchJson(
            "/api/v2/rooms/{$newRoomPublicId}/membership",
            [
                "add" => [
                    ["publicId" => $newUserPublicId1, "level" => 10],
                    ["publicId" => $newUserPublicId2, "level" => 20],
                ]
            ]
        )->assertOk();
        $this->getJson("/api/v2/rooms/{$newRoomPublicId}/membership")
            ->assertOk()
            ->assertJson(
                [
                    ["publicId" => $newUserPublicId1, "userLevel" => 20, "roomLevel" => 10],
                    ["publicId" => $newUserPublicId2, "userLevel" => 30, "roomLevel" => 20],
                ]
            );
        $this->patchJson(
            "/api/v2/rooms/{$newRoomPublicId}/membership",
            [
                "add" => [
                    ["publicId" => $newUserPublicId1, "level" => 30],
                ]
            ]
        )->assertOk();
        // test transaction; add 404s, so remove, which runs first, should be rolled back
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
