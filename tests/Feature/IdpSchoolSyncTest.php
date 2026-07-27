<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessIdpWebhookEvent;
use App\Models\IdpWebhookEvent;
use App\Models\Tenant;
use App\Services\Idp\Dto\IdpEvent;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * `school` events are the only ones that resolve their tenant directly, from
 * `tenants.idp_school_id`. Only the name has a home in aula.
 */
class SchoolSyncTest extends TestCase
{
    use CreatesTestTenant;

    private const string API_URL = 'https://api.eduplaces.test';

    private const string AUTH_URL = 'https://auth.eduplaces.test';

    private const string SCHOOL = 'school-sync-1';

    /** @var array<string, mixed>|null */
    private ?array $idmSchool = null;

    private string $originalName;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        self::$testTenant->refresh();

        $this->originalName = (string) self::$testTenant->name;
        self::$testTenant->update(['idp_school_id' => self::SCHOOL, 'sso_provider' => 'eduplaces']);

        config([
            'idp.providers.eduplaces.auth_url' => self::AUTH_URL,
            'idp.providers.eduplaces.api_url' => self::API_URL,
            'idp.providers.eduplaces.client_id' => 'test-client',
            'idp.providers.eduplaces.client_secret' => 'test-secret',
        ]);

        Cache::flush();
        IdpWebhookEvent::query()->delete();

        $this->idmSchool = null;
        $this->fakeIdm();
    }

    protected function tearDown(): void
    {
        IdpWebhookEvent::query()->delete();
        Tenant::where('name', 'Rival Gymnasium')->where('instance_code', '!=', 'TEST001')->delete();
        Tenant::where('id', self::$testTenant->id)->update([
            'idp_school_id' => null,
            'name' => $this->originalName,
        ]);
        parent::tearDown();
    }

    public function test_renames_the_tenant_to_match_the_school(): void
    {
        $this->setSchool(['name' => 'Schloss Einstein Internat']);

        $this->process($this->event('update', ['name']));

        $this->assertSame('Schloss Einstein Internat', self::$testTenant->fresh()->name);
    }

    public function test_resolves_the_tenant_without_scanning(): void
    {
        $this->setSchool(['name' => 'Schloss Einstein Internat']);

        $this->process($this->event('update', ['name']));

        // idp_school_id is a direct mapping, so no school listing is read.
        $this->assertFalse(
            collect(Http::recorded())->contains(
                fn (array $pair): bool => str_contains((string) $pair[0]->url(), '/people'),
            ),
            'a school event should not trigger a discovery scan',
        );
    }

    public function test_leaves_the_name_alone_when_it_already_matches(): void
    {
        $this->setSchool(['name' => $this->originalName]);
        $event = $this->event('update', ['name']);

        $this->process($event);

        $this->assertSame($this->originalName, self::$testTenant->fresh()->name);
        $this->assertSame(IdpWebhookEvent::STATUS_PROCESSED, $event->fresh()->status);
    }

    public function test_does_not_rename_into_a_name_another_tenant_holds(): void
    {
        Tenant::create([
            'name' => 'Rival Gymnasium',
            'instance_code' => 'RIVAL1',
            'api_base_url' => 'https://rival.example',
            'admin1_username' => 'rival_admin',
            'admin1_email' => 'rival@example.test',
        ]);

        $this->setSchool(['name' => 'Rival Gymnasium']);
        $event = $this->event('update', ['name']);

        $this->process($event);

        // tenants.name is unique: report the clash rather than retrying a write
        // that can never succeed.
        $this->assertSame($this->originalName, self::$testTenant->fresh()->name);
        $this->assertSame(IdpWebhookEvent::STATUS_PROCESSED, $event->fresh()->status);
    }

    public function test_does_not_delete_a_tenant_on_a_school_delete(): void
    {
        $this->setSchool(['name' => 'Schloss Einstein Internat']);
        $event = $this->event('delete');

        $this->process($event);

        // Dropping a tenant destroys a school's whole aula database. That is an
        // operator decision, not something an inbound webhook gets to make.
        $this->assertNotNull(self::$testTenant->fresh());
        $this->assertSame(IdpWebhookEvent::STATUS_SKIPPED, $event->fresh()->status);
        $this->assertSame('school_delete_needs_operator', $event->fresh()->error);
    }

    public function test_skips_a_school_we_do_not_host(): void
    {
        $event = IdpWebhookEvent::create([
            'provider' => 'eduplaces',
            'entity_type' => IdpEvent::ENTITY_SCHOOL,
            'action' => 'update',
            'entity_id' => 'school-somewhere-else',
            'updated_properties' => ['name'],
            'payload' => [],
            'status' => IdpWebhookEvent::STATUS_PENDING,
            'received_at' => now(),
        ]);

        $this->process($event);

        $this->assertSame(IdpWebhookEvent::STATUS_SKIPPED, $event->fresh()->status);
        $this->assertSame('tenant_unresolved', $event->fresh()->error);
    }

    public function test_skips_when_the_school_vanished_upstream(): void
    {
        $event = $this->event('update', ['name']);

        $this->process($event);

        $this->assertSame(IdpWebhookEvent::STATUS_SKIPPED, $event->fresh()->status);
        $this->assertSame('school_not_found_upstream', $event->fresh()->error);
    }

    public function test_processes_an_event_about_attributes_aula_cannot_store(): void
    {
        $this->setSchool([
            'name' => 'Schloss Einstein Internat',
            'address' => ['address' => 'Einsteinallee 1', 'city' => 'Musterstadt', 'zipCode' => '38100'],
            'location' => ['country' => 'DE', 'state' => 'NI'],
            'officialId' => 'DE-NI-123456',
        ]);

        $event = $this->event('update', ['address', 'official_id']);
        $this->process($event);

        // There is nowhere to put an address, but the event is still handled
        // rather than left pending or retried.
        $this->assertSame(IdpWebhookEvent::STATUS_PROCESSED, $event->fresh()->status);
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function process(IdpWebhookEvent $event): void
    {
        $this->app->call([new ProcessIdpWebhookEvent($event->id), 'handle']);
    }

    private function event(string $action, array $properties = []): IdpWebhookEvent
    {
        return IdpWebhookEvent::create([
            'provider' => 'eduplaces',
            'entity_type' => IdpEvent::ENTITY_SCHOOL,
            'action' => $action,
            'entity_id' => self::SCHOOL,
            'updated_properties' => $properties,
            'payload' => ['event' => 'school', 'action' => $action, 'schoolId' => self::SCHOOL],
            'status' => IdpWebhookEvent::STATUS_PENDING,
            'received_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $school
     */
    private function setSchool(array $school): void
    {
        $this->idmSchool = array_merge(['id' => self::SCHOOL], $school);
    }

    private function fakeIdm(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                str_ends_with($path, '/oauth2/token') => Http::response([
                    'access_token' => 'token', 'token_type' => 'bearer', 'expires_in' => 3599,
                ]),
                (bool) preg_match('#/schools/[^/]+/(people|users|groups)$#', $path) => Http::response([]),
                (bool) preg_match('#/schools/([^/]+)$#', $path, $m) => $this->schoolResponse(urldecode($m[1])),
                default => Http::response(status: 404),
            };
        });
    }

    private function schoolResponse(string $schoolId): PromiseInterface
    {
        return $this->idmSchool !== null && $schoolId === self::SCHOOL
            ? Http::response($this->idmSchool)
            : Http::response(status: 404);
    }
}
