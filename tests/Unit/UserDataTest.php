<?php

namespace Tests\Unit;

use App\Data\UserStoreData;
use App\Data\UserUpdateData;
use App\Enums\UserLevel;
use App\Enums\UserStatus;
use Tests\TestCase;

class UserDataTest extends TestCase
{
    const INPUT = [
        'displayname' => 'Firstnamé',
        'username' => 'aula_testuser',
        'realname' => 'Firstnamé Lastname',
        'status' => UserStatus::Active->value,
        'userlevel' => UserLevel::Guest->value,
        'email' => 'featuretest@aula.de',
        'about_me' => 'About me!',
    ];

    public function test_it_casts_properly(): void
    {
        $this->assertTrue(\is_int(self::INPUT['status']));
        $this->assertTrue(\is_int(self::INPUT['userlevel']));
        $userUpdateData = UserUpdateData::from(self::INPUT);
        $this->assertTrue($userUpdateData->userLevel instanceof UserLevel);
        $this->assertTrue($userUpdateData->status instanceof UserStatus);
    }

    public function test_it_has_proper_store_validation_rules(): void
    {
        $rules = UserStoreData::getValidationRules([]);
        $this->assertArrayHasKey('userlevel', $rules);
        $this->assertNotContains('required', $rules['userlevel']);
        $this->assertContains('sometimes', $rules['userlevel']);
        $this->assertTrue(array_any(
            $rules['userlevel'],
            fn ($r) => $r instanceof \Illuminate\Validation\Rules\Enum
        ));
    }
    public function test_it_has_proper_update_validation_rules(): void
    {
        $rules = UserUpdateData::getValidationRules([]);
        $this->assertArrayHasKey('userlevel', $rules);
        $this->assertContains('required', $rules['userlevel']);
        $this->assertNotContains('sometimes', $rules['userlevel']);
        $this->assertTrue(array_any(
            $rules['userlevel'],
            fn ($r) => $r instanceof \Illuminate\Validation\Rules\Enum
        ));
    }
}
