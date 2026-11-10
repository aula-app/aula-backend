<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Concerns\CreatesTestRooms;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;
use Tests\Concerns\CreatesTestTenant;
use App\Models\LegacyUser;
use Laravel\Passport\Passport;

class RoomMembershipTest extends TestCase
{
    use CreatesTestTenant;
    use CreatesTestRooms;
    use CreatesTestUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTestTenantExists();
        $this->ensureTestUsersExist();
        $this->ensureTestRoomsExist();

        Passport::actingAs(self::$testUserAdmin);

        $this->withHeaders([
            ...self::TEST_TENANT_HEADERS
        ]);
    }

    protected function assertArrayEqualsJsonStringWithoutOrder(array $expected, string $actual)
    {
        $decodedActual = json_decode($actual, associative: true);
        return $this->assertEqualsCanonicalizing($expected, $decodedActual);
    }

    public function test_room_membership_notfound()
    {
        $this->getJson(
            "/api/v2/rooms/0123456789abcdef0123456789abcdef/membership"
        )->assertNotFound();
    }

    public function test_room_membership_authz()
    {
        Passport::actingAs(self::$testUserNonAdmin);
        $roomId = self::$testRoom->hash_id;
        $this->getJson(
            "/api/v2/rooms/{$roomId}/membership"
        )->assertForbidden();
        $this->patchJson(
            "/api/v2/rooms/{$roomId}/membership",
            [["add" => [["userPublicId" => self::$testUserNonAdmin->hash_id, "level" => 10]]]]
        )->assertForbidden();
    }

    public function test_room_membership_batch_validation()
    {
        $roomId = self::$testRoom->hash_id;
        foreach([
            [["add" => ["nonempty"]], ["add.0"]],
            [["add" => [["nonempty"]]], ["add.0.userPublicId"]],
            [["add" => [["userPublicId" => "foo"]]], ["add.0.roomMemberRole"]],
            [["add" => [["userPublicId" => "foo", "roomMemberRole" => 666]]], ["add.0.roomMemberRole"]],
            [["add" => [["userPublicId" => "foo", "roomMemberRole" => 10], ["roomMemberRole" => 10]]], ["add.1.userPublicId"]],
        ] as $kv) {
            $this->patchJson(
                "/api/v2/rooms/{$roomId}/membership",
                $kv[0],
            )
                ->assertInvalid($kv[1])
                ->assertUnprocessable();
        }
    }

    public function test_room_membership_batch()
    {
        $roomId = self::$testRoom->hash_id;
        // $user1 = $this->createDistinctUser(UserLevel::User, UserStatus::Active);
        // $user2 = $this->createDistinctUser(UserLevel::Moderator, UserStatus::Active);
        $testUserPublicId = self::$testUserNonAdmin->hash_id;
        $testModeratorPublicId = self::$testUserModerator->hash_id;
        $this->getJson("/api/v2/rooms/{$roomId}/membership")
            ->assertExactJson([]);

        // add user1 and user2 to room
        $this->patchJson(
            "/api/v2/rooms/{$roomId}/membership",
            [
                "add" => [
                    ["userPublicId" => $testUserPublicId, "roomMemberRole" => 10],
                    ["userPublicId" => $testModeratorPublicId, "roomMemberRole" => 20],
                ]
            ]
        )->assertOk();

        // check if their userlevels and roomlevels are correct
        $this->getJson("/api/v2/rooms/{$roomId}/membership")
            ->assertOk()
            ->assertJson(
                [
                    ["publicId" => $testUserPublicId, "userLevel" => 20, "roomMemberRole" => 10],
                    ["publicId" => $testModeratorPublicId, "userLevel" => 30, "roomMemberRole" => 20],
                ]
            );

        // set user1's roomlevel to 30
        $this->patchJson(
            "/api/v2/rooms/{$roomId}/membership",
            [
                "add" => [
                    ["userPublicId" => $testUserPublicId, "roomMemberRole" => 30],
                ]
            ]
        )->assertOk();
        $this->getJson("/api/v2/rooms/{$roomId}/membership")
            ->assertJson(
                [["publicId" => $testUserPublicId, "userLevel" => 20, "roomMemberRole" => 30]]
            );

        // test transaction; `add` will 404, so `remove`, which runs first, should be rolled back
        $this->patchJson(
            "/api/v2/rooms/{$roomId}/membership",
            [
                "add" => [
                    ["userPublicId" => "not_found", "roomMemberRole" => 10],
                ],
                "remove" => [
                    [$testUserPublicId]
                ]
            ]
        )->assertNotFound();
        $this->getJson("/api/v2/rooms/{$roomId}/membership")
            ->assertOk()
            ->assertJson(
                [
                    ["publicId" => $testUserPublicId, "userLevel" => 20, "roomMemberRole" => 30],
                    ["publicId" => $testModeratorPublicId, "userLevel" => 30, "roomMemberRole" => 20],
                ]
            );

        // test remove room
        $this->patchJson(
            "/api/v2/rooms/{$roomId}/membership",
            ["remove" => [$testUserPublicId]]
        )->assertOk();
        $this->getJson("/api/v2/rooms/{$roomId}/membership")
            ->assertJsonMissing([["publicId" => $testUserPublicId]])
            ->assertJson([["publicId" => $testModeratorPublicId]]);

        // test remove user
        $this->deleteJson("/api/v2/users/{$testModeratorPublicId}")
            ->assertOk();
        $this->getJson("/api/v2/rooms/{$roomId}/membership")
            ->assertJson([]);
    }

    public function test_room_membership_batch_404s()
    {
        $roomId = self::$testRoom->hash_id;
        $this->patchJson(
            "/api/v2/rooms/{$roomId}/membership",
            ["add" => [["userPublicId" => "badid", "roomMemberRole" => 10]]],
        )->assertNotFound();
        $this->patchJson(
            "/api/v2/rooms/{$roomId}/membership",
            ["remove" => ["badid"]]
        )->assertNotFound();
    }

    public function test_room_membership_legacy_sync()
    {
        $roomId1 = self::$testRoom->hash_id;
        $roomId2 = self::$testRoom2->hash_id;
        $userId = self::$testUserNonAdmin->hash_id;

        // add user to room

        $this->patchJson(
            "/api/v2/rooms/{$roomId1}/membership",
            [
                "add" => [
                    ["userPublicId" => $userId, "roomMemberRole" => 10],
                ]
            ]
        )->assertOk();
        $this->assertJsonStringEqualsJsonString(
            json_encode([["room" => $roomId1, "role" => 10]]),
            LegacyUser::where('hash_id', $userId)->valueOrFail('roles')
        );

        // add user to another room

        $this->patchJson(
            "/api/v2/rooms/{$roomId2}/membership",
            [
                "add" => [
                    ["userPublicId" => $userId, "roomMemberRole" => 20],
                ]
            ]
        )->assertOk();
        $this->assertArrayEqualsJsonStringWithoutOrder(
            [
                ["room" => $roomId1, "role" => 10],
                ["room" => $roomId2, "role" => 20]
            ],
            LegacyUser::where('hash_id', $userId)->valueOrFail('roles')
        );

        // change user's room role

        $this->patchJson(
            "/api/v2/rooms/{$roomId1}/membership",
            [
                "add" => [
                    ["userPublicId" => $userId, "roomMemberRole" => 30],
                ]
            ]
        )->assertOk();
        $this->assertArrayEqualsJsonStringWithoutOrder(
            [
                ["room" => $roomId1, "role" => 30],
                ["room" => $roomId2, "role" => 20]
            ],
            LegacyUser::where('hash_id', $userId)->valueOrFail('roles')
        );

        // delete room

        $this->deleteJson("/api/v2/rooms/{$roomId1}")->assertOk();
        // checks exact (order, no sub/supersets, only whitespaces diffs ok)
        // so we don't need to assert that the deleted is missing
        $this->assertJsonStringEqualsJsonString(
            json_encode([["room" => $roomId2, "role" => 20]]),
            LegacyUser::where('hash_id', $userId)->valueOrFail('roles')
        );

        // remove user's membership

        $this->patchJson(
            "/api/v2/rooms/{$roomId2}/membership",
            ["remove" => [$userId]]
        )->assertOk();
        $this->assertJsonStringEqualsJsonString(
            json_encode([]),
            LegacyUser::where('hash_id', $userId)->valueOrFail('roles')
        );
    }
}
