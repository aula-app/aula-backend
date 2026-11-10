<?php

declare(strict_types=1);

namespace Tests\Feature;

use DateTimeImmutable;
use Tests\Concerns\CreatesTestRooms;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;
use Tests\Concerns\CreatesTestTenant;
use Laravel\Passport\Passport;
use Illuminate\Support\Facades\DB;

class RoomTest extends TestCase
{
    use CreatesTestTenant;
    use CreatesTestRooms;
    use CreatesTestUsers;

    private const array NEW_ROOM_DATA = [
        'name' => 'Testroom',
        'status' => 1,
        'descriptionPublic' => 'Public test description',
        'descriptionInternal' => 'Internal test description',
        'phaseDurationVoting' => 14,
        'phaseDurationDiscussion' => 14,
    ];

    private const array UPDATE_ROOM_DATA = [
        'name' => 'Testroom renamed',
        'status' => 1,
        'descriptionPublic' => 'Public test description changed',
        'descriptionInternal' => 'Internal test description changed',
        'phaseDurationVoting' => 15,
        'phaseDurationDiscussion' => 15,
    ];

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
            ->assertInvalid(['name', 'status', 'descriptionPublic', 'descriptionInternal', 'phaseDurationVoting', 'phaseDurationDiscussion'])
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
            ['phaseDurationVoting' => ''],
            ['phaseDurationVoting' => -1],
            ['phaseDurationVoting' => 0],
            ['phaseDurationDiscussion' => ''],
        ] as $kv) {
            $this->postJson('/api/v2/rooms', [...self::NEW_ROOM_DATA, ...$kv])
                ->assertInvalid(array_key_first($kv))
                ->assertUnprocessable();
        }
    }

    public function test_update_room()
    {
        $roomId = self::$testRoom->hash_id;
        $result = $this->putJson(
            "/api/v2/rooms/{$roomId}",
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
    }

    public function test_update_room_required()
    {
        $roomId = self::$testRoom->hash_id;
        $this->putJson(
            "/api/v2/rooms/$roomId",
            []
        )
            ->assertInvalid(['name', 'status', 'descriptionPublic', 'descriptionInternal', 'phaseDurationVoting', 'phaseDurationDiscussion'])
            ->assertUnprocessable();
    }

    public function test_create_update_room_authz()
    {
        Passport::actingAs(self::$testUserNonAdmin);
        $roomId = self::$testRoom->hash_id;
        $this->getJson('/api/v2/rooms')
            ->assertForbidden();
        $this->postJson(
            '/api/v2/rooms',
            self::NEW_ROOM_DATA,
        )->assertForbidden();
        $this->putJson(
            "/api/v2/rooms/$roomId",
            self::UPDATE_ROOM_DATA,
        )->assertForbidden();
    }

    public function test_index_read_delete_room_authz()
    {
        Passport::actingAs(self::$testUserNonAdmin);
        $roomId = self::$testRoom->hash_id;
        $this->getJson("/api/v2/rooms")
            ->assertForbidden();
        $this->getJson("/api/v2/rooms/$roomId")
            ->assertForbidden();
        $this->deleteJson("/api/v2/rooms/$roomId")
            ->assertForbidden();
    }

    public function test_index_room()
    {
        $allRooms = $this->getJson("/api/v2/rooms")
            ->assertOk()
            ->json();
        $allRoomPublicIds = array_column($allRooms, 'publicId');
        $this->assertContains(self::$testRoom->hash_id, $allRoomPublicIds);
    }

    public function test_room_delete()
    {
        $roomId = self::$testRoom->hash_id;
        $this->deleteJson("/api/v2/rooms/{$roomId}")
            ->assertOk();
        $this->assertEquals(
            0,
            DB::table('au_rooms')
                ->whereIn('hash_id', [$roomId])
                ->count()
        );
    }

    public function test_show_room()
    {
        $roomId = self::$testRoom->hash_id;
        $this->getJson("/api/v2/rooms/$roomId")
            ->assertOk()
            ->assertJson(self::NEW_ROOM_DATA);
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

    public function test_patch_disallowed()
    {
        $roomId = self::$testRoom->hash_id;
        $this->patchJson("/api/v2/rooms/{$roomId}", [])
            ->assertMethodNotAllowed();
    }

}
