<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Filament\Resources\TenantResource\Pages\CreateTenant;
use App\Filament\Resources\TenantResource\Pages\EditTenant;
use App\Models\LegacyUser;
use App\Models\Manager\AulaManagerUser;
use App\Models\Tenant;
use App\UseCases\CreateTenantUseCase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class TenantResourcePagesTest extends TestCase
{
    use CreatesTestTenant;

    private const string ADMIN1 = 'pages.admin1';

    // Called directly: submitting the form would create a tenant database.
    public function test_create_passes_every_form_field_to_the_use_case_by_name(): void
    {
        $tenant = new Tenant;
        $received = null;

        $mock = Mockery::mock(CreateTenantUseCase::class);
        $mock->shouldReceive('execute')
            ->once()
            ->withArgs(function (...$args) use (&$received): bool {
                $received = $args;

                return true;
            })
            ->andReturn($tenant);
        app()->instance(CreateTenantUseCase::class, $mock);

        $page = new CreateTenant;
        $method = new \ReflectionMethod($page, 'handleRecordCreation');

        $result = $method->invoke($page, [
            'name' => 'Form School',
            'instance_code' => 'FRM01',
            'is_name_public' => false,
            'api_base_url' => 'https://frm01.example',
            'contact_info' => '',
            'school_type_id' => '3',
            'admin1_name' => 'Admin One',
            'admin1_username' => 'admin1',
            'admin1_email' => 'admin1@frm01.example',
            'admin1_username_manual' => true,
            'admin2_name' => null,
            'admin2_username' => 'admin2',
            'admin2_email' => 'admin2@frm01.example',
            'admin2_username_manual' => false,
            'sso_enabled' => true,
            'sso_provider' => 'eduplaces',
            'sso_force_logout' => false,
            'sso_required' => false,
            'idp_migration_status' => Tenant::IDP_MIGRATION_FLAGGED,
            'sso_require_email_verified' => true,
            // Not a form field; must not reach the use case.
            'jwt_key' => 'injected',
        ]);

        $this->assertSame($tenant, $result);
        // Mockery hands named arguments over in parameter order.
        $this->assertSame([
            'Form School',              // name
            'FRM01',                    // instanceCode
            'admin1',                   // admin1Username
            'Admin One',                // admin1FullName
            'admin1@frm01.example',     // admin1Email
            'admin2',                   // admin2Username
            'admin2',                   // admin2FullName, falls back to the username
            'admin2@frm01.example',     // admin2Email
            null,                       // adminPassword
            'https://frm01.example',    // apiBaseUrl
            false,                      // isNamePublic
            null,                       // contactInfo, empty string becomes null
            3,                          // schoolTypeId, cast from the select's string
            true,                       // ssoEnabled
            'eduplaces',                // ssoProvider
            false,                      // ssoForceLogout
            false,                      // ssoRequired
            true,                       // ssoRequireEmailVerified
            Tenant::IDP_MIGRATION_FLAGGED, // idpMigrationStatus
        ], $received);
    }

    public function test_release_action_shows_the_new_setup_link_and_hides_itself(): void
    {
        $this->ensureTestTenantExists();
        $tenant = self::$testTenant->fresh();
        $this->cleanRelease($tenant);
        $tenant->update([
            'idp_school_id' => 'school-pages-test',
            'sso_provider' => 'eduplaces',
            'sso_required' => true,
            'admin1_username' => self::ADMIN1,
            'admin1_init_pass_url' => null,
        ]);
        $adminId = $tenant->run(fn (): int => $this->seedAdmin());
        $operator = AulaManagerUser::firstOrCreate(
            ['email' => 'pages@test.example'],
            ['name' => 'pages', 'password' => bcrypt('pages-test-password')],
        );

        try {
            Livewire::actingAs($operator, 'web')
                ->test(EditTenant::class, ['record' => $tenant->getKey()])
                ->assertActionVisible('releaseIdpSchool')
                ->callAction('releaseIdpSchool')
                ->assertHasNoActionErrors()
                ->assertActionHidden('releaseIdpSchool')
                ->assertFormSet(function (array $state) use ($tenant, $adminId): void {
                    $secret = $tenant->run(fn () => DB::table('au_change_password')->where('user_id', $adminId)->value('secret'));

                    $this->assertNotNull($secret);
                    $this->assertSame("https://test001.example/password/{$secret}?code=TEST001", $state['admin1_init_pass_url']);
                    $this->assertNull($state['idp_school_id']);
                    $this->assertFalse($state['sso_required']);
                });
        } finally {
            $this->cleanRelease($tenant);
            $tenant->update([
                'idp_school_id' => null,
                'sso_provider' => null,
                'sso_required' => false,
                'admin1_username' => 'phpunit_admin',
                'admin1_init_pass_url' => null,
            ]);
            $operator->delete();
        }
    }

    private function seedAdmin(): int
    {
        $user = new LegacyUser;
        $user->username = self::ADMIN1;
        $user->displayname = self::ADMIN1;
        $user->email = self::ADMIN1.'@test.example';
        $user->pw = '';
        $user->sso_sub = 'kc-sub-pages-admin1';
        $user->status = UserStatus::Active;
        $user->userlevel = 50;
        $user->hash_id = md5(self::ADMIN1);
        $user->save();

        return (int) $user->id;
    }

    private function cleanRelease(Tenant $tenant): void
    {
        $tenant->run(function (): void {
            $ids = LegacyUser::where('username', self::ADMIN1)->pluck('id');
            DB::table('au_change_password')->whereIn('user_id', $ids)->delete();
            LegacyUser::whereIn('id', $ids)->delete();
        });
    }
}
