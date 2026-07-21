<?php

namespace Tests\Unit;

use App\Services\Idp\DirectoryException;
use App\Services\Idp\Providers\Eduplaces\EduplacesDirectory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Eduplaces implementation of IdentityDirectory.
 *
 * Its job is to hide Eduplaces' choreography behind the contract: two
 * overlapping endpoints for people, a per-group call to reach member names, and
 * a client-credentials token in front of all of it.
 */
class EduplacesDirectoryTest extends TestCase
{
    private const string AUTH_URL = 'https://auth.eduplaces.test';

    private const string API_URL = 'https://api.eduplaces.test';

    private EduplacesDirectory $directory;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'idp.providers.eduplaces.auth_url' => self::AUTH_URL,
            'idp.providers.eduplaces.api_url' => self::API_URL,
            'idp.providers.eduplaces.client_id' => 'test-client',
            'idp.providers.eduplaces.client_secret' => 'test-secret',
            'idp.providers.eduplaces.scopes' => ['urn:eduplaces:idm:v1:users:read'],
        ]);

        Cache::flush();

        $this->directory = app(EduplacesDirectory::class);
    }

    public function test_reads_a_user(): void
    {
        Http::fake([
            self::AUTH_URL.'/oauth2/token' => Http::response($this->token()),
            self::API_URL.'/idm/ep/v1/users/*' => Http::response([
                'id' => 'user-1',
                'status' => 'ACTIVE',
                'role' => 'STUDENT',
                'pseudonym' => 'Denk Raumfahrer',
                'groups' => [['id' => 'group-2', 'name' => 'Klasse 10a', 'status' => 'ACTIVE']],
            ]),
        ]);

        $user = $this->directory->user('user-1');

        $this->assertNotNull($user);
        $this->assertSame('ACTIVE', $user->status);
        // No `name` on this endpoint — the pseudonym is what there is.
        $this->assertSame('Denk Raumfahrer', $user->displayName());
        $this->assertNull($user->realName());
        $this->assertSame(['group-2'], $user->groupIds());
    }

    public function test_merges_the_people_and_users_views_of_one_person(): void
    {
        // Eduplaces splits a person across two endpoints and neither is
        // complete: names and sourceSystemIdentifier on one, status and
        // pseudonym on the other.
        Http::fake([
            self::AUTH_URL.'/oauth2/token' => Http::response($this->token()),
            self::API_URL.'/idm/ep/v1/people/*' => Http::response([
                'id' => 'person-1',
                'role' => 'TEACHER',
                'name' => ['firstFull' => 'Stephanie', 'firstCall' => 'Stephanie', 'last' => 'Schuster'],
                'sourceSystemIdentifier' => '123xyz',
            ]),
            self::API_URL.'/idm/ep/v1/users/*' => Http::response([
                'id' => 'person-1',
                'status' => 'ACTIVE',
                'pseudonym' => 'Denk Kapitaen',
            ]),
        ]);

        $user = $this->directory->personOrUser('person-1');

        $this->assertNotNull($user);
        $this->assertSame('Stephanie Schuster', $user->displayName());
        $this->assertSame('123xyz', $user->sourceSystemIdentifier);
        $this->assertSame('ACTIVE', $user->status);
        $this->assertSame('Denk Kapitaen', $user->pseudonym);
    }

    public function test_falls_back_to_users_when_people_is_not_granted(): void
    {
        Http::fake([
            self::AUTH_URL.'/oauth2/token' => Http::response($this->token()),
            self::API_URL.'/idm/ep/v1/schools/*/people' => Http::response(status: 403),
            self::API_URL.'/idm/ep/v1/schools/*/users' => Http::response([
                ['id' => 'user-1', 'pseudonym' => 'Bio Akrobat', 'role' => 'STUDENT'],
            ]),
        ]);

        $users = $this->directory->users('school-1');

        // `people:read` is a separate scope an app may not hold; losing it must
        // not lose the school.
        $this->assertCount(1, $users);
        $this->assertSame('Bio Akrobat', $users[0]->displayName());
    }

    public function test_reads_each_group_in_full_to_reach_member_names(): void
    {
        Http::fake([
            self::AUTH_URL.'/oauth2/token' => Http::response($this->token()),
            self::API_URL.'/idm/ep/v1/schools/*/groups' => Http::response([
                ['id' => 'group-1', 'name' => 'Klasse 5a'],
            ]),
            self::API_URL.'/idm/ep/v1/groups/*' => Http::response([
                'id' => 'group-1',
                'name' => 'Klasse 5a',
                'members' => [
                    ['id' => 'person-1', 'role' => 'TEACHER', 'name' => ['firstCall' => 'Stephanie', 'last' => 'Schuster']],
                ],
            ]),
        ]);

        $groups = $this->directory->groups('school-1');

        // The school listing has no members; only the per-group call does.
        $this->assertCount(1, $groups);
        $this->assertSame('Klasse 5a', $groups[0]->name);
        $this->assertSame(['person-1'], $groups[0]->memberIds());
        $this->assertSame('Stephanie Schuster', $groups[0]->members[0]->displayName());
    }

    public function test_reads_a_school(): void
    {
        Http::fake([
            self::AUTH_URL.'/oauth2/token' => Http::response($this->token()),
            self::API_URL.'/idm/ep/v1/schools/*' => Http::response([
                'id' => 'school-1',
                'name' => 'Schloss Einstein Internat',
                'address' => ['city' => 'Musterstadt'],
                'officialId' => 'DE-NI-123456',
            ]),
        ]);

        $school = $this->directory->school('school-1');

        $this->assertNotNull($school);
        $this->assertSame('Schloss Einstein Internat', $school->name);
        $this->assertSame('DE-NI-123456', $school->officialId);
    }

    public function test_returns_null_when_the_entity_is_gone(): void
    {
        Http::fake([
            self::AUTH_URL.'/oauth2/token' => Http::response($this->token()),
            self::API_URL.'/idm/ep/v1/users/*' => Http::response(status: 404),
        ]);

        $this->assertNull($this->directory->user('missing'));
    }

    public function test_throws_on_a_server_error(): void
    {
        Http::fake([
            self::AUTH_URL.'/oauth2/token' => Http::response($this->token()),
            self::API_URL.'/idm/ep/v1/users/*' => Http::response(status: 500),
        ]);

        $this->expectException(DirectoryException::class);

        $this->directory->user('user-1');
    }

    public function test_reuses_a_cached_token(): void
    {
        Http::fake([
            self::AUTH_URL.'/oauth2/token' => Http::response($this->token()),
            self::API_URL.'/idm/ep/v1/users/*' => Http::response(['id' => 'user-1']),
        ]);

        $this->directory->user('user-1');
        $this->directory->user('user-1');

        Http::assertSentCount(3);
    }

    public function test_retries_once_with_a_fresh_token_after_a_401(): void
    {
        Http::fake([
            self::AUTH_URL.'/oauth2/token' => Http::sequence()
                ->push($this->token('stale-token'))
                ->push($this->token('fresh-token')),
            self::API_URL.'/idm/ep/v1/users/*' => Http::sequence()
                ->push(status: 401)
                ->push(['id' => 'user-1']),
        ]);

        $this->assertNotNull($this->directory->user('user-1'));
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer fresh-token'));
    }

    public function test_fails_when_credentials_are_not_configured(): void
    {
        config(['idp.providers.eduplaces.client_secret' => null]);

        $this->expectException(DirectoryException::class);

        app(EduplacesDirectory::class)->user('user-1');
    }

    /**
     * @return array<string, mixed>
     */
    private function token(string $token = 'test-token'): array
    {
        return ['access_token' => $token, 'token_type' => 'bearer', 'expires_in' => 3599];
    }
}
