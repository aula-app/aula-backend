<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Idp\Providers\Eduplaces\EduplacesDirectory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * The IDM client is used from inside tenancy (the school import runs there),
 * and production runs `CACHE_STORE=database`.
 *
 * Reading the token cache through `tenancy()->central()` ended tenancy
 * mid-call, purging the dynamically-created `tenant` connection while the
 * database cache store still held it — "Database connection [tenant] not
 * configured", a 500 on the SSO callback. The rest of the suite runs on the
 * file store, so nothing else covers this.
 */
class IdpDatabaseCacheTest extends TestCase
{
    use CreatesTestTenant;

    private const string API_URL = 'https://api.eduplaces.test';

    private const string AUTH_URL = 'https://auth.eduplaces.test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();

        config([
            // Explicitly the production driver, not phpunit.xml's file store.
            'cache.default' => 'database',
            'idp.providers.eduplaces.auth_url' => self::AUTH_URL,
            'idp.providers.eduplaces.api_url' => self::API_URL,
            'idp.providers.eduplaces.client_id' => 'test-client',
            'idp.providers.eduplaces.client_secret' => 'test-secret',
        ]);

        // The database cache outlives a test method, and the token key is
        // tenant-prefixed, so it has to be cleared inside tenancy.
        self::$testTenant->run(fn () => Cache::flush());
    }

    public function test_fetches_through_a_database_cache_while_tenancy_is_active(): void
    {
        Http::fake([
            self::AUTH_URL.'/oauth2/token' => Http::response([
                'access_token' => 'token', 'token_type' => 'bearer', 'expires_in' => 3599,
            ]),
            self::API_URL.'/idm/ep/v1/people/*' => Http::response(['id' => 'person-1', 'name' => ['last' => 'Cache']]),
            self::API_URL.'/idm/ep/v1/users/*' => Http::response(['id' => 'person-1', 'status' => 'ACTIVE']),
        ]);

        $person = self::$testTenant->run(
            fn () => (app(EduplacesDirectory::class))->personOrUser('person-1')
        );

        $this->assertNotNull($person);
        $this->assertSame('person-1', $person->id);
    }

    public function test_a_second_client_reuses_the_cached_token_inside_tenancy(): void
    {
        Http::fake([
            self::AUTH_URL.'/oauth2/token' => Http::response([
                'access_token' => 'token', 'token_type' => 'bearer', 'expires_in' => 3599,
            ]),
            self::API_URL.'/idm/ep/v1/people/*' => Http::response(['id' => 'person-1', 'name' => []]),
            self::API_URL.'/idm/ep/v1/users/*' => Http::response(['id' => 'person-1']),
        ]);

        self::$testTenant->run(function () {
            (app(EduplacesDirectory::class))->personOrUser('person-1');
            // A fresh instance, so the in-memory copy cannot help: this has to
            // come back through the cache without unwinding tenancy.
            (app(EduplacesDirectory::class))->personOrUser('person-1');
        });

        // One token request; each lookup reads both people and users.
        Http::assertSentCount(5);
    }

    public function test_writing_the_token_cache_does_not_break_the_tenant_connection(): void
    {
        Http::fake([
            self::AUTH_URL.'/oauth2/token' => Http::response([
                'access_token' => 'token', 'token_type' => 'bearer', 'expires_in' => 3599,
            ]),
            self::API_URL.'/idm/ep/v1/people/*' => Http::response(['id' => 'person-1', 'name' => []]),
            self::API_URL.'/idm/ep/v1/users/*' => Http::response(['id' => 'person-1']),
        ]);

        $stillUsable = self::$testTenant->run(function () {
            (app(EduplacesDirectory::class))->personOrUser('person-1');

            // The import keeps writing to tenant tables after the first API
            // call; the connection has to survive the cache write.
            return DB::table('au_rooms')->count() >= 0;
        });

        $this->assertTrue($stillUsable);
    }
}
