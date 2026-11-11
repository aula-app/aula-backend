<?php

declare(strict_types=1);

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Filament\Actions\TemporaryPasswordsAction;
use App\Filament\Resources\TenantResource\Pages\ListTenants;
use App\Models\LegacyUser;
use App\Models\Manager\AulaManagerUser;
use App\Models\Tenant;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

const PANEL_TEST_USERNAME = 'tmppanel.student';

afterEach(function () {
    Tenant::where('instance_code', 'TEST001')->first()
        ?->run(fn () => DB::table('au_users_basedata')->where('username', PANEL_TEST_USERNAME)->delete());
});

it('downloads the temporary passwords pdf from the tenants table', function () {
    $manager = AulaManagerUser::create([
        'name' => 'Temp Password Admin',
        'email' => 'temp-password-panel@test.local',
        'password' => bcrypt('password'),
    ]);
    $tenant = Tenant::where('instance_code', 'TEST001')->firstOrFail();
    $tenant->run(function () {
        $user = new LegacyUser;
        $user->username = PANEL_TEST_USERNAME;
        $user->realname = 'Lena Panel';
        $user->displayname = 'Lena Panel';
        $user->status = UserStatus::Active;
        $user->userlevel = UserLevel::User;
        $user->hash_id = md5(PANEL_TEST_USERNAME);
        $user->save();
    });

    $this->actingAs($manager, 'web');
    $action = TestAction::make(TemporaryPasswordsAction::NAME)->table($tenant);

    Livewire::test(ListTenants::class)
        ->assertActionVisible($action)
        ->callAction($action)
        ->assertHasNoErrors()
        ->assertFileDownloaded();

    $tenant->run(function () {
        expect(LegacyUser::where('username', PANEL_TEST_USERNAME)->first()->temp_pw)->not->toBeEmpty();
    });
});
