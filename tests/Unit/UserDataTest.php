<?php

namespace Tests\Unit;

use App\Data\UserModelData;
use App\Data\UserStoreData;
use App\Data\UserUpdateData;
use App\Enums\UserLevel;
use App\Enums\UserStatus;
use DateTimeImmutable;
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
        $this->assertEquals(UserLevel::Guest, $userUpdateData->userLevel);
        $this->assertTrue($userUpdateData->status instanceof UserStatus);
        $this->assertEquals(UserStatus::Active, $userUpdateData->status);
    }

    public function test_it_casts_dates_properly(): void
    {
        $nowCarbon = new \Illuminate\Support\Carbon;
        $userModelData = UserModelData::from([
            'id' => 123,
            'hash_id' => '123abc',
            ...self::INPUT,
            'created' => $nowCarbon,
            'last_update' => $nowCarbon,
        ]);
        $this->assertTrue($userModelData->createdAt instanceof DateTimeImmutable);
        $this->assertTrue($userModelData->updatedAt instanceof DateTimeImmutable);
        $this->assertEquals($nowCarbon->toAtomString(), $userModelData->createdAt->format(DATE_ATOM));
    }

    public function test_it_has_proper_store_validation_rules(): void
    {
        $rules = UserStoreData::getValidationRules([]);
        $this->assertArrayHasKey('userlevel', $rules);
        $this->assertNotContains('required', $rules['userlevel']);
        $this->assertContains('nullable', $rules['userlevel']);
        $this->assertContains('missing', $rules['created_at']);
        $this->assertNotContains('sometimes', $rules['created_at']);
        $this->assertContains('missing', $rules['updated_at']);
        $this->assertNotContains('sometimes', $rules['updated_at']);
        $this->assertContains('missing', $rules['public_id']);
        $this->assertNotContains('sometimes', $rules['public_id']);
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
        $this->assertContains('missing', $rules['created_at']);
        $this->assertNotContains('sometimes', $rules['created_at']);
        $this->assertContains('missing', $rules['updated_at']);
        $this->assertNotContains('sometimes', $rules['updated_at']);
        $this->assertContains('missing', $rules['public_id']);
        $this->assertNotContains('sometimes', $rules['public_id']);
        $this->assertTrue(array_any(
            $rules['userlevel'],
            fn ($r) => $r instanceof \Illuminate\Validation\Rules\Enum
        ));
    }
}
