<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\IdpDirectoryEntry;
use App\Services\Idp\Dto\IdpEvent;
use App\Services\Idp\TenantResolver;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class TenantResolverTest extends TestCase
{
    use CreatesTestTenant;

    private const string API_URL = 'https://api.eduplaces.test';

    private const string AUTH_URL = 'https://auth.eduplaces.test';

    private const string SCHOOL = 'school-resolver-1';

    private TenantResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        // Shared across test classes, so the in-memory copy goes stale and
        // Eloquent would skip writes it wrongly believes are no-ops.
        self::$testTenant->refresh();
        self::$testTenant->update(['idp_school_id' => self::SCHOOL, 'sso_provider' => 'eduplaces']);

        config([
            'idp.providers.eduplaces.auth_url' => self::AUTH_URL,
            'idp.providers.eduplaces.api_url' => self::API_URL,
            'idp.providers.eduplaces.client_id' => 'test-client',
            'idp.providers.eduplaces.client_secret' => 'test-secret',
        ]);

        Cache::flush();
        IdpDirectoryEntry::query()->delete();

        $this->resolver = $this->app->make(TenantResolver::class);
    }

    protected function tearDown(): void
    {
        IdpDirectoryEntry::query()->delete();
        // TEST001 is shared across test classes and is not torn down, so
        // anything set on it here has to be put back.
        self::$testTenant->update(['idp_school_id' => null]);
        parent::tearDown();
    }

    public function test_resolves_a_school_directly_from_the_tenant_column(): void
    {
        $tenant = $this->resolver->resolve('eduplaces', IdpEvent::ENTITY_SCHOOL, self::SCHOOL);

        $this->assertNotNull($tenant);
        $this->assertSame(self::$testTenant->id, $tenant->id);
    }

    public function test_returns_null_for_a_school_we_do_not_host(): void
    {
        $this->assertNull($this->resolver->resolve('eduplaces', IdpEvent::ENTITY_SCHOOL, 'school-elsewhere'));
    }

    public function test_resolves_a_person_from_the_directory_without_calling_the_api(): void
    {
        Http::fake();
        $this->resolver->remember(IdpDirectoryEntry::TYPE_USER, 'person-indexed', self::$testTenant->id, 'eduplaces', 'eduplaces');

        $tenant = $this->resolver->resolve('eduplaces', IdpEvent::ENTITY_USER, 'person-indexed');

        $this->assertNotNull($tenant);
        $this->assertSame(self::$testTenant->id, $tenant->id);
        Http::assertNothingSent();
    }

    public function test_discovers_an_unindexed_person_by_scanning_schools(): void
    {
        $this->fakeSchoolListings();

        $tenant = $this->resolver->resolve('eduplaces', IdpEvent::ENTITY_USER, 'person-b');

        $this->assertNotNull($tenant);
        $this->assertSame(self::$testTenant->id, $tenant->id);
    }

    public function test_a_scan_indexes_every_person_and_group_it_saw(): void
    {
        $this->fakeSchoolListings();

        $this->resolver->resolve('eduplaces', IdpEvent::ENTITY_USER, 'person-b');

        // The scan cost one round of API calls, and every other id at the
        // school is now answerable from idp_directory alone.
        $this->assertDirectoryHas(IdpDirectoryEntry::TYPE_USER, 'person-a');
        $this->assertDirectoryHas(IdpDirectoryEntry::TYPE_USER, 'person-b');
        // Present in the `/users` listing and absent from `/people`.
        $this->assertDirectoryHas(IdpDirectoryEntry::TYPE_USER, 'user-only-c');
        // Indexed from the group refs nested in the user payload.
        $this->assertDirectoryHas(IdpDirectoryEntry::TYPE_GROUP, 'group-a');
    }

    public function test_a_second_lookup_after_a_scan_hits_the_index(): void
    {
        $this->fakeSchoolListings();

        $this->resolver->resolve('eduplaces', IdpEvent::ENTITY_USER, 'person-b');
        $callsAfterScan = count(Http::recorded());

        $this->resolver->resolve('eduplaces', IdpEvent::ENTITY_USER, 'person-a');

        $this->assertCount($callsAfterScan, Http::recorded(), 'the indexed lookup should not have called the API');
    }

    public function test_discovers_an_unindexed_group(): void
    {
        $this->fakeSchoolListings();

        $tenant = $this->resolver->resolve('eduplaces', IdpEvent::ENTITY_GROUP, 'group-b');

        $this->assertNotNull($tenant);
        $this->assertSame(self::$testTenant->id, $tenant->id);
    }

    public function test_returns_null_for_a_person_at_a_school_we_do_not_host(): void
    {
        $this->fakeSchoolListings();

        $this->assertNull($this->resolver->resolve('eduplaces', IdpEvent::ENTITY_USER, 'person-stranger'));
    }

    public function test_does_not_rescan_for_an_id_already_known_to_be_unresolvable(): void
    {
        $this->fakeSchoolListings();

        $this->resolver->resolve('eduplaces', IdpEvent::ENTITY_USER, 'person-stranger');
        $callsAfterFirstScan = count(Http::recorded());

        $this->resolver->resolve('eduplaces', IdpEvent::ENTITY_USER, 'person-stranger');

        // A school this installation does not host must not make each of its
        // events start a fresh scan.
        $this->assertCount($callsAfterFirstScan, Http::recorded());
    }

    public function test_drops_a_directory_entry_pointing_at_a_missing_tenant(): void
    {
        $this->fakeSchoolListings();
        $this->resolver->remember(IdpDirectoryEntry::TYPE_USER, 'person-orphan', 'tenant-that-no-longer-exists', 'eduplaces');

        $this->assertNull($this->resolver->resolve('eduplaces', IdpEvent::ENTITY_USER, 'person-orphan'));
        $this->assertSame(0, IdpDirectoryEntry::where('idp_id', 'person-orphan')->count());
    }

    public function test_remembering_an_id_again_repoints_it(): void
    {
        $this->resolver->remember(IdpDirectoryEntry::TYPE_USER, 'person-moved', 'old-tenant', 'eduplaces');
        $this->resolver->remember(IdpDirectoryEntry::TYPE_USER, 'person-moved', self::$testTenant->id, 'eduplaces');

        $this->assertSame(1, IdpDirectoryEntry::where('idp_id', 'person-moved')->count());
        $this->assertSame(
            self::$testTenant->id,
            IdpDirectoryEntry::where('idp_id', 'person-moved')->firstOrFail()->tenant_id,
        );
    }

    public function test_a_failing_school_does_not_abort_the_scan(): void
    {
        Http::fake([
            self::AUTH_URL.'/oauth2/token' => Http::response([
                'access_token' => 'token', 'token_type' => 'bearer', 'expires_in' => 3599,
            ]),
            self::API_URL.'/idm/ep/v1/schools/*/people' => Http::response(status: 503),
            self::API_URL.'/idm/ep/v1/schools/*/users' => Http::response(status: 503),
        ]);

        // No DirectoryException escapes: an unreachable school is logged and
        // stepped over so one failure does not abort the scan.
        $this->assertNull($this->resolver->resolve('eduplaces', IdpEvent::ENTITY_USER, 'person-b'));
    }

    public function test_returns_null_for_an_unknown_event_type(): void
    {
        $this->assertNull($this->resolver->resolve('eduplaces', 'planet', 'planet-1'));
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function fakeSchoolListings(): void
    {
        $groups = [
            ['id' => 'group-a', 'name' => 'Klasse A'],
            ['id' => 'group-b', 'name' => 'Klasse B'],
        ];

        $people = [
            ['id' => 'person-a', 'name' => ['last' => 'A'], 'groups' => [$groups[0]]],
            ['id' => 'person-b', 'name' => ['last' => 'B'], 'groups' => []],
        ];

        // A closure, because groups() reads each group in full, so the response
        // depends on which group was requested.
        Http::fake(function (Request $request) use ($groups, $people) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                str_ends_with($path, '/oauth2/token') => Http::response([
                    'access_token' => 'token', 'token_type' => 'bearer', 'expires_in' => 3599,
                ]),
                (bool) preg_match('#/schools/[^/]+/people$#', $path) => Http::response($people),
                (bool) preg_match('#/schools/[^/]+/users$#', $path) => Http::response([
                    ['id' => 'user-only-c', 'name' => ['last' => 'C'], 'groups' => []],
                ]),
                (bool) preg_match('#/schools/[^/]+/groups$#', $path) => Http::response($groups),
                (bool) preg_match('#/groups/([^/]+)$#', $path, $m) => $this->groupDetail($groups, urldecode($m[1])),
                default => Http::response(status: 404),
            };
        });
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     */
    private function groupDetail(array $groups, string $id): PromiseInterface
    {
        foreach ($groups as $group) {
            if ($group['id'] === $id) {
                return Http::response($group + ['members' => []]);
            }
        }

        return Http::response(status: 404);
    }

    private function assertDirectoryHas(string $type, string $id): void
    {
        $this->assertSame(
            1,
            IdpDirectoryEntry::where('entity_type', $type)->where('idp_id', $id)->count(),
            "{$type} {$id} should have been indexed by the scan",
        );
    }
}
