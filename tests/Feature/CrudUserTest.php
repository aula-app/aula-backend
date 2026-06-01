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
        // 'status' => 1,
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
            '/api/v2/user',
            self::NEW_USER_DATA,
        )
            // why not Created any more??
            // ->assertCreated()
            ->assertOk()
            ->assertJson(['data' => self::NEW_USER_DATA]);
        $newUserId1 = $newUserResult->decodeResponseJson()['data']['id'];
        $this->assertGreaterThan(0, $newUserId1);

        // create with optional
        $newUserResult = $this->post(
            '/api/v2/user',
            [...self::NEW_USER_DATA, ...self::USER_DATA_UPDATE],
        )
            ->assertOk()
            ->assertJson(['data' => self::USER_DATA_UPDATE]);
        $newUserId2 = $newUserResult->decodeResponseJson()['data']['id'];

        // show
        $this->getJson('/api/v2/user/'.$newUserId1)
            ->assertOk()
            ->assertJson(['data' => self::NEW_USER_DATA]);

        // index
        $allUsers = $this->getJson('/api/v2/user/');
        $allUsers->assertOk()->decodeResponseJson();

        $allUserIds = array_column($allUsers['data'], 'id');
        $this->assertContains($newUserId1, $allUserIds);
        $this->assertContains($newUserId2, $allUserIds);

        // update/put
        $changedUserData = self::NEW_USER_DATA;
        $changedUserData['realname'] = 'Changed Name';

        $this->putJson(
            '/api/v2/user/'.$newUserId1,
            $changedUserData,
        )
            ->assertOk()
            ->assertJson(['data' => $changedUserData]);

        /* TODO update/patch?
        $patchUserData = ['realname' => 'Changed Name'];
        $this->patchJson(
            '/api/v2/user/'.$newUserId,
            $patchUserData,
        )
            ->assertOk();
        */

        // delete
        $this->deleteJson('/api/v2/user/'.$newUserId1, [])
            ->assertOk();
        $this->deleteJson('/api/v2/user/'.$newUserId2, [])
            ->assertOk();
    }

    public function test_create_validation()
    {
        $this->postJson('/api/v2/user', [...self::NEW_USER_DATA, ...['username' => null]],
            )->assertUnprocessable();
        $this->postJson('/api/v2/user', [...self::NEW_USER_DATA, ...['username' => '']],
            )->assertUnprocessable();
        $this->postJson('/api/v2/user', [...self::NEW_USER_DATA, ...['displayname' => str_repeat('A', 500)]])
            ->assertUnprocessable();
        $this->postJson('/api/v2/user', [...self::NEW_USER_DATA, ...['email' => 'bad@mail_huh.com']])
            ->assertUnprocessable();
        $this->postJson('/api/v2/user', [...self::NEW_USER_DATA, ...['userlevel' => '1000']])
            ->assertUnprocessable();
        $this->postJson('/api/v2/user', [...self::NEW_USER_DATA, ...['userlevel' => 1000]])
            ->assertUnprocessable();
        $this->postJson('/api/v2/user', [...self::NEW_USER_DATA, ...['status' => 5]])
            ->assertUnprocessable();
        $this->postJson('/api/v2/user', [...self::NEW_USER_DATA, ...['status' => '5']])
            ->assertUnprocessable();
    }

    public function test_bad_deletes()
    {
        $this->deleteJson('/api/v2/user/1000000', [])
            ->assertNotFound();
        $this->deleteJson('/api/v2/user/foo', [])
            ->assertNotFound();
    }
}
