<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\LegacyUser;
use Tests\Concerns\CreatesTestTenant;

trait CreatesTestUsers
{
    use CreatesTestTenant;

    private static ?LegacyUser $testUserAdmin = null;
    private static ?LegacyUser $testUserNonAdmin = null;
    private static ?LegacyUser $testUserModerator= null;

    public function createDistinctUser(UserLevel $userLevel, UserStatus $userStatus): LegacyUser
    {
        return self::$testTenant->run(function () use ($userLevel, $userStatus) {
            $email = "e2e_distinct_{$userLevel->name}_" . bin2hex(random_bytes(8)) . '@aula.de';
            $user = new LegacyUser();
            $user->email = $email;
            $user->displayname = 'Distinct';
            $user->realname = 'Distinct User';
            $user->about_me = 'About me.';
            $user->sso_sub = null;
            $user->status = $userStatus;
            $user->username = $email;
            $user->hash_id = md5($email);
            $user->userlevel = $userLevel;
            $user->roles = json_encode([]);
            $user->refresh_token = false;
            $user->save();
            return $user;
        });
    }

    protected function ensureTestUsersExist(): void
    {
        $this->ensureTestTenantExists();

        self::$testTenant->run(function () {
            self::$testUserAdmin = LegacyUser::where('hash_id', md5('test_admin'))->first()
                ?? new LegacyUser();
            self::$testUserAdmin->username = 'test_admin';
            self::$testUserAdmin->email = 'e2e_test_admin@aula.de';
            self::$testUserAdmin->displayname = 'Test Admin';
            self::$testUserAdmin->realname = 'Test Admin';
            self::$testUserAdmin->about_me = 'About me.';
            self::$testUserAdmin->sso_sub = null;
            self::$testUserAdmin->status = UserStatus::Active;
            self::$testUserAdmin->hash_id = md5('test_admin');
            self::$testUserAdmin->userlevel = UserLevel::Admin;
            self::$testUserAdmin->roles = json_encode([]);
            self::$testUserAdmin->refresh_token = false;
            self::$testUserAdmin->save();

            self::$testUserNonAdmin = LegacyUser::where('hash_id', md5('test_nonadmin'))->first()
                ?? new LegacyUser();
            self::$testUserNonAdmin->username = 'test_nonadmin';
            self::$testUserNonAdmin->email = 'e2e_test_nonadmin@aula.de';
            self::$testUserNonAdmin->displayname = 'Test Nonadmin';
            self::$testUserNonAdmin->realname = 'Test Nonadmin';
            self::$testUserNonAdmin->about_me = 'About me.';
            self::$testUserNonAdmin->sso_sub = null;
            self::$testUserNonAdmin->status = UserStatus::Active;
            self::$testUserNonAdmin->hash_id = md5('test_nonadmin');
            self::$testUserNonAdmin->userlevel = UserLevel::User;
            self::$testUserNonAdmin->roles = json_encode([]);
            self::$testUserNonAdmin->refresh_token = false;
            self::$testUserNonAdmin->save();

            self::$testUserModerator = LegacyUser::where('hash_id', md5('test_moderator'))->first()
                ?? new LegacyUser();
            self::$testUserModerator->username = 'test_moderator';
            self::$testUserModerator->email = 'e2e_test_moderator@aula.de';
            self::$testUserModerator->displayname = 'Test Moderator';
            self::$testUserModerator->realname = 'Test Moderator';
            self::$testUserModerator->about_me = 'About me.';
            self::$testUserModerator->sso_sub = null;
            self::$testUserModerator->status = UserStatus::Active;
            self::$testUserModerator->hash_id = md5('test_moderator');
            self::$testUserModerator->userlevel = UserLevel::Moderator;
            self::$testUserModerator->roles = json_encode([]);
            self::$testUserModerator->refresh_token = false;
            self::$testUserModerator->save();
        });
    }
}
