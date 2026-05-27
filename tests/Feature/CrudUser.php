<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class CrudUser extends TestCase
{
    use CreatesTestTenant;
    private const array NEW_USER_DATA = [
        'displayname' => 'Mx McTestfacé',
        'username' => 'aula_testuser',
        'realname' => 'Testy McTestfacé',
    ];
    private const array HEADERS = ['aula-instance-code' => 'TEST001'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
    }

    public function test_crud()
    {
        $tenant = self::$testTenant;
        $this->assertNotNull($tenant);

        // create
        $newUserResult = $this->post(
            '/api/v2/user',
            self::NEW_USER_DATA,
            self::HEADERS,
        )
            ->assertCreated()
            ->assertJson(['data' => self::NEW_USER_DATA]);

        $newUserId = $newUserResult->decodeResponseJson()['data']['id'];

        // show
        $this->getJson(
            '/api/v2/user/'.$newUserId,
            self::HEADERS,
        )
            ->assertOk()
            ->assertJson(['data' => self::NEW_USER_DATA]);

        // index
        $allUsers = $this->getJson(
            '/api/v2/user/',
            self::HEADERS,
        )
            ->assertOk()
            ->decodeResponseJson();
        $this->assertContains($newUserId, array_column($allUsers['data'], 'id'));

        // update/put
        $changedUserData = self::NEW_USER_DATA;
        $changedUserData['realname'] = 'Changed Name';

        $this->putJson(
            '/api/v2/user/'.$newUserId,
            $changedUserData,
            self::HEADERS,
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
            '/api/v2/user/'.$newUserId,
            [],
            self::HEADERS,
        )
            ->assertOk();
    }

    public function test_bad_create()
    {
        $this->postJson(
            '/api/v2/user',
            [
                ...self::NEW_USER_DATA,
                ...['username' => null]
            ],
            ['aula-instance-code' => 'TEST001'],
        )->assertUnprocessable();
    }

    public function test_bad_deletes()
    {
        $this->deleteJson(
            '/api/v2/user/1000000',
            [],
            ['aula-instance-code' => 'TEST001'],
        )
            ->assertNotFound();
        $this->deleteJson(
            '/api/v2/user/foo',
            [],
            ['aula-instance-code' => 'TEST001'],
        )
            // validate & BadRequest?
            ->assertNotFound();
    }
}
