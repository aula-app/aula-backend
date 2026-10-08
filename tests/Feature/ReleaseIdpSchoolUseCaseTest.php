<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\IdpDirectoryEntry;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\UseCases\ReleaseIdpSchoolUseCase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class ReleaseIdpSchoolUseCaseTest extends TestCase
{
    use CreatesTestTenant;

    private const string ADMIN1 = 'release.admin1';

    private const string ADMIN2 = 'release.admin2';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        $this->tenant = self::$testTenant->fresh();
        $this->clean();
        $this->tenant->update([
            'idp_school_id' => 'school-release-test',
            'sso_provider' => 'eduplaces',
            'sso_required' => true,
            'admin1_username' => self::ADMIN1,
            'admin2_username' => self::ADMIN2,
            'admin1_init_pass_url' => null,
            'admin2_init_pass_url' => null,
        ]);
        IdpDirectoryEntry::create(['provider' => 'eduplaces', 'entity_type' => 'user', 'idp_id' => 'p1', 'tenant_id' => $this->tenant->id]);
    }

    protected function tearDown(): void
    {
        $this->clean();
        $this->tenant->update([
            'idp_school_id' => null,
            'sso_provider' => null,
            'sso_required' => false,
            'admin1_username' => 'phpunit_admin',
            'admin2_username' => null,
            'admin1_init_pass_url' => null,
            'admin2_init_pass_url' => null,
        ]);
        parent::tearDown();
    }

    public function test_it_hands_the_tenant_back_to_password_login(): void
    {
        [$admin1Id, $admin2Id] = $this->seedAdmins();
        $importedId = $this->seedImportedUser();

        app(ReleaseIdpSchoolUseCase::class)->execute($this->tenant);

        $row = $this->tenant->fresh();
        $this->assertNull($row->idp_school_id);
        $this->assertSame('eduplaces', $row->sso_provider);
        $this->assertFalse((bool) $row->sso_required);
        $this->assertSame(0, IdpDirectoryEntry::where('tenant_id', $row->id)->count());

        $this->tenant->run(function () use ($row, $admin1Id, $admin2Id, $importedId): void {
            foreach ([$admin1Id => $row->admin1_init_pass_url, $admin2Id => $row->admin2_init_pass_url] as $userId => $url) {
                $secret = DB::table('au_change_password')->where('user_id', $userId)->value('secret');
                $this->assertNotNull($secret);
                $this->assertSame("https://test001.example/password/{$secret}?code=TEST001", $url);

                $admin = LegacyUser::findOrFail($userId);
                $this->assertNull($admin->sso_sub);
                $this->assertNull($admin->idp_user_id);
            }

            // Other users are untouched.
            $imported = LegacyUser::findOrFail($importedId);
            $this->assertSame('kc-sub-imported', $imported->sso_sub);
            $this->assertSame('person-imported', $imported->idp_user_id);
            $this->assertSame(0, DB::table('au_change_password')->where('user_id', $importedId)->count());
        });
    }

    public function test_it_replaces_an_earlier_secret_for_the_admin(): void
    {
        [$admin1Id] = $this->seedAdmins();
        $this->tenant->run(fn () => DB::table('au_change_password')->insert([
            'user_id' => $admin1Id, 'secret' => 'stale-secret', 'created_at' => now(),
        ]));

        app(ReleaseIdpSchoolUseCase::class)->execute($this->tenant);

        $this->tenant->run(function () use ($admin1Id): void {
            $secrets = DB::table('au_change_password')->where('user_id', $admin1Id)->pluck('secret');
            $this->assertCount(1, $secrets);
            $this->assertNotSame('stale-secret', $secrets[0]);
        });
    }

    public function test_an_admin_the_tenant_no_longer_has_gets_no_link(): void
    {
        $this->seedAdmins();
        $this->tenant->update(['admin2_username' => 'release.gone']);

        app(ReleaseIdpSchoolUseCase::class)->execute($this->tenant);

        $row = $this->tenant->fresh();
        $this->assertNotNull($row->admin1_init_pass_url);
        $this->assertNull($row->admin2_init_pass_url);
        $this->assertNull($row->idp_school_id);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function seedAdmins(): array
    {
        return $this->tenant->run(fn (): array => [
            $this->seedUser(self::ADMIN1, 'kc-sub-admin1', 'person-admin1'),
            $this->seedUser(self::ADMIN2, 'kc-sub-admin2', 'person-admin2'),
        ]);
    }

    private function seedImportedUser(): int
    {
        return $this->tenant->run(fn (): int => $this->seedUser('release.imported', 'kc-sub-imported', 'person-imported'));
    }

    private function seedUser(string $username, string $ssoSub, string $idpUserId): int
    {
        $user = new LegacyUser;
        $user->username = $username;
        $user->displayname = $username;
        $user->email = "{$username}@test.example";
        $user->pw = '';
        $user->sso_sub = $ssoSub;
        $user->idp_user_id = $idpUserId;
        $user->status = UserStatus::Active;
        $user->userlevel = 50;
        $user->hash_id = md5($username);
        $user->save();

        return (int) $user->id;
    }

    private function clean(): void
    {
        IdpDirectoryEntry::where('tenant_id', $this->tenant->id)->delete();
        $this->tenant->run(function (): void {
            $ids = LegacyUser::where('username', 'like', 'release.%')->pluck('id');
            DB::table('au_change_password')->whereIn('user_id', $ids)->delete();
            LegacyUser::whereIn('id', $ids)->delete();
        });
    }
}
