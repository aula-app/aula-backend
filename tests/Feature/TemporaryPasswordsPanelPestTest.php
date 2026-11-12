<?php

declare(strict_types=1);

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Filament\Actions\TemporaryPasswordsAction;
use App\Filament\Resources\TenantResource;
use App\Filament\Resources\TenantResource\Pages\ListTenants;
use App\Filament\Resources\TenantResource\Pages\TemporaryPasswords;
use App\Models\LegacyUser;
use App\Models\Manager\AulaManagerUser;
use App\Models\Tenant;
use App\Services\TemporaryPasswordService;
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

function seedPanelStudent(Tenant $tenant): int
{
    return (int) $tenant->run(function () {
        $user = new LegacyUser;
        $user->username = PANEL_TEST_USERNAME;
        $user->realname = 'Lena Panel';
        $user->displayname = 'Lena Panel';
        $user->status = UserStatus::Active;
        $user->userlevel = UserLevel::User;
        $user->hash_id = md5(PANEL_TEST_USERNAME);
        $user->save();

        return $user->id;
    });
}

beforeEach(function () {
    $this->actingAs(AulaManagerUser::create([
        'name' => 'Temp Password Admin',
        'email' => 'temp-password-panel@test.local',
        'password' => bcrypt('password'),
    ]), 'web');
    $this->tenant = Tenant::where('instance_code', 'TEST001')->firstOrFail();
    seedPanelStudent($this->tenant);
});

it('links the tenants table to the temporary passwords page', function () {
    Livewire::test(ListTenants::class)
        ->assertActionVisible(TestAction::make(TemporaryPasswordsAction::NAME)->table($this->tenant))
        ->assertActionHasUrl(
            TestAction::make(TemporaryPasswordsAction::NAME)->table($this->tenant),
            TenantResource::getUrl('temp-passwords', ['record' => $this->tenant]),
        );
});

it('lists the rooms on the temporary passwords page', function () {
    $this->get(TenantResource::getUrl('temp-passwords', ['record' => $this->tenant]))
        ->assertOk()
        ->assertSee(TemporaryPasswordService::NO_ROOM_LABEL)
        // The button must carry a call the browser can run.
        ->assertSee('wire:click="downloadRoom(\'none\')"', false)
        ->assertDontSee('@js', false)
        ->assertSee('Download all (ZIP)');
});

it('downloads the pdf of one room', function () {
    Livewire::test(TemporaryPasswords::class, ['record' => $this->tenant->getKey()])
        ->call('downloadRoom', TemporaryPasswordService::NO_ROOM_KEY)
        ->assertHasNoErrors()
        ->assertFileDownloaded('without-room.pdf');

    $this->tenant->run(function () {
        expect(LegacyUser::where('username', PANEL_TEST_USERNAME)->first()->temp_pw)->not->toBeEmpty();
    });
});

it('downloads all rooms as a zip', function () {
    Livewire::test(TemporaryPasswords::class, ['record' => $this->tenant->getKey()])
        ->callAction('downloadAll')
        ->assertHasNoErrors()
        ->assertFileDownloaded();
})->skip(! class_exists(ZipArchive::class), 'ext-zip is not installed.');

it('does not open the page for an sso-only tenant', function () {
    $this->tenant->update(['sso_required' => true]);

    $this->get(TenantResource::getUrl('temp-passwords', ['record' => $this->tenant]))->assertNotFound();
});
