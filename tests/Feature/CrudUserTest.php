<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
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

    private const array HEADERS = ['aula-instance-code' => 'TEST001'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        $this->withHeaders(self::HEADERS);
    }

    public function test_create()
    {
        $result = $this->post(
            '/api/v2/users',
            self::NEW_USER_DATA,
        )
            ->assertCreated()
            ->assertJson(self::NEW_USER_DATA);
        $newUserDecoded = $result->decodeResponseJson();
        $this->assertIsString($newUserDecoded['created_at']);
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $newUserDecoded['created_at']));
        $newUserHashId = $newUserDecoded['hash_id'];
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $newUserHashId);
        return $newUserHashId;
    }

    public function test_create_optional()
    {
        $result = $this->post(
            '/api/v2/users',
            [...self::NEW_USER_DATA, ...self::USER_DATA_UPDATE],
        )
            ->assertCreated()
            ->assertJson(self::USER_DATA_UPDATE);
        $newUserHashId = $result->decodeResponseJson()['hash_id'];
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $newUserHashId);
        return $newUserHashId;
    }

    #[Depends('test_create')]
    public function test_show($newUserHashId)
    {
        $this->getJson('/api/v2/users/'.$newUserHashId)
            ->assertOk()
            ->assertJson(self::NEW_USER_DATA);
    }

    #[Depends('test_create_optional')]
    public function test_show_optional($newUserHashId)
    {
        $this->getJson('/api/v2/users/'.$newUserHashId)
            ->assertOk()
            ->assertJson([...self::NEW_USER_DATA, ...self::USER_DATA_UPDATE]);
    }

    #[Depends('test_create')]
    #[Depends('test_create_optional')]
    public function test_index($newUserHashId1, $newUserHashId2)
    {
        $allUsers = $this->getJson('/api/v2/users/')
            ->assertOk()->json();

        $allUserHashIds = array_column($allUsers, 'hash_id');
        $this->assertContains($newUserHashId1, $allUserHashIds);
        $this->assertContains($newUserHashId2, $allUserHashIds);
    }

    #[Depends('test_create')]
    public function test_update($newUserHashId)
    {
        $changedUserData = [
            ...self::NEW_USER_DATA,
            ...self::USER_DATA_UPDATE,
            ...['realname' => 'Changed Name'],
        ];

        $result = $this->putJson(
            '/api/v2/users/'.$newUserHashId,
            $changedUserData,
        )
            ->assertOk()
            ->assertJson($changedUserData);
        $updatedUserDecoded = $result->decodeResponseJson();
        $this->assertIsString($updatedUserDecoded['updated_at']);
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $updatedUserDecoded['updated_at']));
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $updatedUserDecoded['created_at']));
        $this->assertGreaterThanOrEqual($updatedUserDecoded['created_at'], $updatedUserDecoded['updated_at']);
    }

    #[Depends('test_create')]
    public function test_update_required($newUserHashId)
    {
        $result = $this->putJson(
            '/api/v2/users/'.$newUserHashId,
            self::NEW_USER_DATA
        );
        $result
            ->assertInvalid(['email', 'userlevel', 'about_me'])
            ->assertUnprocessable();
    }

    #[Depends('test_create')]
    public function test_update_validation($newUserHashId)
    {
        $changedUserData = [
            ...self::NEW_USER_DATA,
            ...self::USER_DATA_UPDATE,
            ...['email' => 'bad@mail_huh.com'],
            ...['userlevel' => 1000],
        ];
        $this->putJson(
            '/api/v2/users/'.$newUserHashId,
            $changedUserData,
        )
            ->assertInvalid(['email', 'userlevel'])
            ->assertUnprocessable();
    }

    #[Depends('test_create')]
    #[Depends('test_create_optional')]
    public function test_delete($newUserHashId1, $newUserHashId2)
    {
        $this->deleteJson('/api/v2/users/'.$newUserHashId1, [])
            ->assertOk();
        $this->deleteJson('/api/v2/users/'.$newUserHashId2, [])
            ->assertOk();
    }

    public function test_create_validation()
    {
        foreach([
            ['created_at' => '2001-01-23T12:34:56Z'],
            ['created_at' => 'nondate'],
            ['created_at' => ''],
            // created, last_update, id, hash_id musst be *missing* from request
            ['id' => ''],
            ['hash_id' => ''],
            ['hash_id' => null],
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
    public function test_patch_disallowed($newUserHashId)
    {
        // need an existing user, PATCH to /api/v2/users/foo will 404 before 405ing
        $this->patchJson('/api/v2/users/'.$newUserHashId, [])
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
