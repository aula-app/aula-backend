<?php

namespace Tests\Unit;

use App\Enums\UserStatus;
use App\Models\LegacyUser;
use Tests\TestCase;

class LegacyUserUnitTest extends TestCase
{
    public function test_legacy_user_password_verification_bcrypt(): void
    {
        $user = new LegacyUser();
        $user->pw = password_hash('correct_password', PASSWORD_DEFAULT);
        $user->temp_pw = null;

        $this->assertTrue($user->checkPassword('correct_password'));
        $this->assertFalse($user->checkPassword('wrong_password'));
    }

    public function test_legacy_user_password_verification_temp_pw(): void
    {
        $user = new LegacyUser();
        $user->pw = password_hash('hashed_password', PASSWORD_DEFAULT);
        $user->temp_pw = 'temp123';

        $this->assertTrue($user->checkPassword('temp123'));
        $this->assertTrue($user->checkPassword('hashed_password'));
        $this->assertFalse($user->checkPassword('wrong'));
    }

    public function test_legacy_user_status_checks(): void
    {
        $user = new LegacyUser();

        $user->status = UserStatus::Active;
        $this->assertTrue($user->isActive());

        $user->status = UserStatus::Inactive;
        $this->assertFalse($user->isActive());

        $user->status = UserStatus::Suspended;
        $this->assertFalse($user->isActive());

        $user->status = UserStatus::Archived;
        $this->assertFalse($user->isActive());
    }

    public function test_legacy_user_refresh_token_flag(): void
    {
        $user = new LegacyUser();

        $user->refresh_token = false;
        $this->assertFalse($user->needsRefresh());

        $user->refresh_token = true;
        $this->assertTrue($user->needsRefresh());
    }
}
