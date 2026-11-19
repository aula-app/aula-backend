<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\UseCases\CreateTenantUseCase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CreateTenantUseCaseTest extends TestCase
{
    private const string INSTANCE_CODE = 'UCT01';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropTenant();
    }

    protected function tearDown(): void
    {
        $this->dropTenant();
        parent::tearDown();
    }

    public function test_it_writes_the_optional_columns_it_is_given(): void
    {
        $tenant = $this->execute(
            adminPassword: null,
            apiBaseUrl: 'https://uct01.example',
            isNamePublic: false,
            contactInfo: 'Call the office',
            ssoEnabled: true,
            ssoProvider: 'eduplaces',
            ssoForceLogout: false,
            ssoRequired: true,
            ssoRequireEmailVerified: false,
            idpMigrationStatus: Tenant::IDP_MIGRATION_FLAGGED,
        );

        $row = $this->row();

        $this->assertSame('https://uct01.example', $row->api_base_url);
        $this->assertStringStartsWith('https://uct01.example/password/', (string) $tenant->admin1_init_pass_url);
        $this->assertSame(0, (int) $row->is_name_public);
        $this->assertSame('Call the office', $row->contact_info);
        $this->assertSame(1, (int) $row->sso_enabled);
        $this->assertSame('eduplaces', $row->sso_provider);
        $this->assertSame(0, (int) $row->sso_force_logout);
        $this->assertSame(1, (int) $row->sso_required);
        $this->assertSame(0, (int) $row->sso_require_email_verified);
        $this->assertSame(Tenant::IDP_MIGRATION_FLAGGED, $row->idp_migration_status);
    }

    public function test_a_null_optional_leaves_the_column_on_its_database_default(): void
    {
        $this->execute();

        $row = $this->row();

        $this->assertSame(config('app.url'), $row->api_base_url);
        $this->assertSame(1, (int) $row->is_name_public);
        $this->assertNull($row->contact_info);
        $this->assertNull($row->school_type_id);
        $this->assertSame(0, (int) $row->sso_enabled);
        $this->assertNull($row->sso_provider);
        $this->assertSame(1, (int) $row->sso_force_logout);
        $this->assertSame(0, (int) $row->sso_required);
        $this->assertSame(1, (int) $row->sso_require_email_verified);
        $this->assertNull($row->idp_migration_status);
    }

    private function execute(mixed ...$optional): Tenant
    {
        return app(CreateTenantUseCase::class)->execute(...[
            'name' => 'Use Case Tenant',
            'instanceCode' => self::INSTANCE_CODE,
            'admin1Username' => 'uct_admin1',
            'admin1FullName' => 'Admin One',
            'admin1Email' => 'admin1@uct01.example',
            'admin2Username' => 'uct_admin2',
            'admin2FullName' => 'Admin Two',
            'admin2Email' => 'admin2@uct01.example',
            'adminPassword' => 'secret',
            ...$optional,
        ]);
    }

    private function row(): object
    {
        $row = DB::connection(config('tenancy.database.central_connection'))
            ->table('tenants')
            ->where('instance_code', self::INSTANCE_CODE)
            ->first();

        $this->assertNotNull($row);

        return $row;
    }

    private function dropTenant(): void
    {
        $tenant = Tenant::where('instance_code', self::INSTANCE_CODE)->first();

        if ($tenant === null) {
            return;
        }

        $database = $tenant->database()->getName();
        $tenant->delete();
        DB::connection(config('tenancy.database.central_connection'))
            ->statement("DROP DATABASE IF EXISTS `{$database}`");
    }
}
