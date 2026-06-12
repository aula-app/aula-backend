<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

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

    public function test_crud()
    {
        // create
        $newUserResult = $this->post(
            '/api/v2/users',
            self::NEW_USER_DATA,
        )
            ->assertCreated()
            ->assertJson(self::NEW_USER_DATA);
        $newUserDecoded = $newUserResult->decodeResponseJson();
        // TODO: too weak
        $this->assertIsString($newUserDecoded['created']);
        $newUserHashId1 = $newUserDecoded['hash_id'];
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $newUserHashId1);

        // create with optional
        $newUserResult = $this->post(
            '/api/v2/users',
            [...self::NEW_USER_DATA, ...self::USER_DATA_UPDATE],
        )
            ->assertCreated()
            ->assertJson(self::USER_DATA_UPDATE);
        $newUserHashId2 = $newUserResult->decodeResponseJson()['hash_id'];
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $newUserHashId2);

        // show
        $this->getJson('/api/v2/users/'.$newUserHashId1)
            ->assertOk()
            ->assertJson(self::NEW_USER_DATA);

        // index
        $allUsers = $this->getJson('/api/v2/users/')
            ->assertOk()->json();

        $allUserHashIds = array_column($allUsers, 'hash_id');
        $this->assertContains($newUserHashId1, $allUserHashIds);
        $this->assertContains($newUserHashId2, $allUserHashIds);

        // update (put, no patch)
        $changedUserData = [
            ...self::NEW_USER_DATA,
            ...self::USER_DATA_UPDATE,
            ...['realname' => 'Changed Name'],
        ];

        $this->putJson(
            '/api/v2/users/'.$newUserHashId1,
            $changedUserData,
        )
            ->assertOk()
            ->assertJson($changedUserData);

        // test update required
        $result = $this->putJson(
            '/api/v2/users/'.$newUserHashId1,
            self::NEW_USER_DATA
        );
        $result
            ->assertInvalid(['email', 'userlevel', 'about_me'])
            ->assertUnprocessable();

        // test update validation
        $changedUserData['email'] = 'bad@mail_huh.com';
        $this->putJson(
            '/api/v2/users/'.$newUserHashId1,
            $changedUserData,
        )
            ->assertInvalid(['email'])
            ->assertUnprocessable();

        // delete
        $this->deleteJson('/api/v2/users/'.$newUserHashId1, [])
            ->assertOk();
        $this->deleteJson('/api/v2/users/'.$newUserHashId2, [])
            ->assertOk();
    }

    public function test_create_validation()
    {
        $this->postJson('/api/v2/users', [...self::NEW_USER_DATA, ...['created' => '2001-01-23T12:34:56Z']])
            ->assertInvalid(['created'])
            ->assertUnprocessable();
        $this->postJson('/api/v2/users', [...self::NEW_USER_DATA, ...['created' => 'nondate']])
            ->assertInvalid(['created'])
            ->assertUnprocessable();
        $r = $this->postJson('/api/v2/users', [...self::NEW_USER_DATA, ...['created' => '']])
            ->assertInvalid(['created'])
            ->assertUnprocessable();
        $this->postJson('/api/v2/users', [...self::NEW_USER_DATA, ...['username' => null]])
            ->assertInvalid(['username'])
            ->assertUnprocessable();
        $this->postJson('/api/v2/users', [...self::NEW_USER_DATA, ...['username' => '']])
            ->assertInvalid(['username'])
            ->assertUnprocessable();
        $this->postJson('/api/v2/users', [...self::NEW_USER_DATA, ...['displayname' => str_repeat('A', 500)]])
            ->assertInvalid(['displayname'])
            ->assertUnprocessable();
        $this->postJson('/api/v2/users', [...self::NEW_USER_DATA, ...['email' => 'bad@mail_huh.com']])
            ->assertInvalid(['email'])
            ->assertUnprocessable();
        $this->postJson('/api/v2/users', [...self::NEW_USER_DATA, ...['userlevel' => '1000']])
            ->assertInvalid(['userlevel'])
            ->assertUnprocessable();
        $this->postJson('/api/v2/users', [...self::NEW_USER_DATA, ...['userlevel' => 1000]])
            ->assertInvalid(['userlevel'])
            ->assertUnprocessable();
        $this->postJson('/api/v2/users', [...self::NEW_USER_DATA, ...['status' => 5]])
            ->assertInvalid(['status'])
            ->assertUnprocessable();
        $this->postJson('/api/v2/users', [...self::NEW_USER_DATA, ...['status' => '5']])
            ->assertInvalid(['status'])
            ->assertUnprocessable();
    }

    public function test_patch_disallowed()
    {
        $this->patchJson('/api/v2/users/foo', [])
            ->assertMethodNotAllowed();
    }

    public function test_bad_deletes()
    {
        $this->deleteJson('/api/v2/users/1000000', [])
            ->assertNotFound();
        $this->deleteJson('/api/v2/users/foo', [])
            ->assertNotFound();
    }
}
