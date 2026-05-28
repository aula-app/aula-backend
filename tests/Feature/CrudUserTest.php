<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserLevel;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class CrudUserTest extends TestCase
{
    use CreatesTestTenant;
    private const array NEW_USER_DATA = [
        'displayname' => 'Mx McTestfacé',
        'username' => 'aula_testuser',
        'realname' => 'Testy McTestfacé',
        'email' => 'featuretest@aula.de',
    ];
    private const array USER_DATA_UPDATE = [
        'userlevel' => UserLevel::Guest->value,
        'status' => 1,
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
        $tenant = self::$testTenant;
        $this->assertNotNull($tenant);

        // create
        $newUserResult = $this->post(
            '/api/v2/user',
            self::NEW_USER_DATA,
            // self::HEADERS,
        )
            ->assertCreated()
            ->assertJson(['data' => self::NEW_USER_DATA]);
        $newUserId1 = $newUserResult->decodeResponseJson()['data']['id'];

        // create with optional
        $newUserResult = $this->post(
            '/api/v2/user',
            [...self::NEW_USER_DATA, ...self::USER_DATA_UPDATE],
            // self::HEADERS,
        )
            ->assertCreated()
            ->assertJson(['data' => self::USER_DATA_UPDATE]);
        $newUserId2 = $newUserResult->decodeResponseJson()['data']['id'];

        // show
        $this->getJson(
            '/api/v2/user/'.$newUserId1,
            // self::HEADERS,
        )
            ->assertOk()
            ->assertJson(['data' => self::NEW_USER_DATA]);

        // index
        $allUsers = $this->getJson(
            '/api/v2/user/',
            // self::HEADERS,
        )
            ->assertOk()
            ->decodeResponseJson();
        $allUserIds = array_column($allUsers['data'], 'id');
        $this->assertContains($newUserId1, $allUserIds);
        $this->assertContains($newUserId2, $allUserIds);

        // update/put
        $changedUserData = self::NEW_USER_DATA;
        $changedUserData['realname'] = 'Changed Name';

        $this->putJson(
            '/api/v2/user/'.$newUserId1,
            $changedUserData,
            // self::HEADERS,
        )
            ->assertOk()
            ->assertJson(['data' => $changedUserData]);

        /* TODO update/patch?
        $patchUserData = ['realname' => 'Changed Name'];
        $this->patchJson(
            '/api/v2/user/'.$newUserId,
            $patchUserData,
            self::HEADERS,
        )
            ->assertOk();
        */

        // delete
        $this->deleteJson(
            '/api/v2/user/'.$newUserId1,
            [],
            // self::HEADERS,
        )
            ->assertOk();
        $this->deleteJson(
            '/api/v2/user/'.$newUserId2,
            [],
            // self::HEADERS,
        )
            ->assertOk();
    }

    public function test_create_validation()
    {
        $tenant = self::$testTenant;
        $this->assertNotNull($tenant);

        $this->postJson(
            '/api/v2/user',
            [
                ...self::NEW_USER_DATA,
                ...['username' => null]
            ],
            self::HEADERS,
        )->assertUnprocessable();
        $this->postJson('/api/v2/user', [...self::NEW_USER_DATA, ...['displayname' => str_repeat('A', 500)]])
            ->assertUnprocessable();
        $this->postJson('/api/v2/user', [...self::NEW_USER_DATA, ...['email' => 'bad@mail_huh.com']])
            ->assertUnprocessable();
        $this->postJson('/api/v2/user', [...self::NEW_USER_DATA, ...['userlevel' => '1000']])
            ->assertUnprocessable();
        $this->postJson('/api/v2/user', [...self::NEW_USER_DATA, ...['status' => '5']])
            ->assertUnprocessable();
    }

    public function test_bad_deletes()
    {
        $tenant = self::$testTenant;
        $this->assertNotNull($tenant);

        $this->deleteJson(
            '/api/v2/user/1000000',
            [],
            self::HEADERS,
        )
            ->assertNotFound();
        $this->deleteJson(
            '/api/v2/user/foo',
            [],
            self::HEADERS,
        )
            // validate & BadRequest?
            ->assertNotFound();
    }
}
