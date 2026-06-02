<?php

namespace Tests\Feature\Models;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\LegacyUser;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class LegacyUserTest extends TestCase
{
    use CreatesTestTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
    }

    public function test_userlevel_persists_as_integer_and_is_cast_back_to_enum(): void
    {
        $tenant = self::$testTenant;
        $this->assertNotNull($tenant);

        $result = $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_enum_user')->delete();

            $user = new LegacyUser();
            $user->username = 'phpunit_enum_user';
            $user->pw = password_hash('secret123', PASSWORD_DEFAULT);
            $user->status = UserStatus::Active->value;
            $user->hash_id = 'phpunit_enum_'.uniqid();
            $user->userlevel = UserLevel::PrincipalPlus;
            $user->roles = json_encode([]);
            $user->refresh_token = false;
            $user->save();

            $userId = $user->id;
            $rawUserLevel = DB::table('au_users_basedata')
                ->where('id', $userId)
                ->value('userlevel');

            $freshUser = LegacyUser::findOrFail($userId);

            LegacyUser::where('id', $userId)->delete();

            return [
                'raw' => (int) $rawUserLevel,
                'casted' => $freshUser->userlevel,
            ];
        });

        $this->assertSame(UserLevel::PrincipalPlus->value, $result['raw']);
        $this->assertSame(UserLevel::PrincipalPlus, $result['casted']);
    }
}
