<?php

namespace Tests\Unit;

use App\Data\User\DomainUserData;
use App\Data\User\Requests\StoreUserData;
use App\Data\User\Requests\UpdateUserData;
use App\Enums\UserLevel;
use App\Enums\UserStatus;
use DateTimeImmutable;
use Tests\TestCase;

class UserDataTest extends TestCase
{
    public const INPUT = [
        'displayName' => 'Firstnamé',
        'userName' => 'aula_testuser',
        'realName' => 'Firstnamé Lastname',
        'status' => UserStatus::Active->value,
        'userLevel' => UserLevel::Guest->value,
        'email' => 'featuretest@aula.de',
        'aboutMe' => 'About me!',
    ];

    public function test_it_casts_properly(): void
    {
        $this->assertTrue(\is_int(self::INPUT['status']));
        $this->assertTrue(\is_int(self::INPUT['userLevel']));
        $userUpdateData = UpdateUserData::from(self::INPUT);
        $this->assertTrue($userUpdateData->userLevel instanceof UserLevel);
        $this->assertEquals(UserLevel::Guest, $userUpdateData->userLevel);
        $this->assertTrue($userUpdateData->status instanceof UserStatus);
        $this->assertEquals(UserStatus::Active, $userUpdateData->status);
    }

    public function test_it_casts_dates_properly(): void
    {
        $nowCarbon = new \Illuminate\Support\Carbon();
        $userModelData = DomainUserData::from([
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
        $rules = StoreUserData::getValidationRules([]);
        $this->assertArrayHasKey('userLevel', $rules);
        $this->assertNotContains('required', $rules['userLevel']);
        $this->assertContains('nullable', $rules['userLevel']);
        $this->assertContains('missing', $rules['createdAt']);
        $this->assertNotContains('sometimes', $rules['createdAt']);
        $this->assertContains('missing', $rules['updatedAt']);
        $this->assertNotContains('sometimes', $rules['updatedAt']);
        $this->assertContains('missing', $rules['publicId']);
        $this->assertNotContains('sometimes', $rules['publicId']);
        $this->assertTrue(array_any(
            $rules['userLevel'],
            fn ($r) => $r instanceof \Illuminate\Validation\Rules\Enum
        ));
    }
    public function test_it_has_proper_update_validation_rules(): void
    {
        $rules = UpdateUserData::getValidationRules([]);
        $this->assertArrayHasKey('userLevel', $rules);
        $this->assertContains('required', $rules['userLevel']);
        $this->assertNotContains('sometimes', $rules['userLevel']);
        $this->assertContains('missing', $rules['createdAt']);
        $this->assertNotContains('sometimes', $rules['createdAt']);
        $this->assertContains('missing', $rules['updatedAt']);
        $this->assertNotContains('sometimes', $rules['updatedAt']);
        $this->assertContains('missing', $rules['publicId']);
        $this->assertNotContains('sometimes', $rules['publicId']);
        $this->assertTrue(array_any(
            $rules['userLevel'],
            fn ($r) => $r instanceof \Illuminate\Validation\Rules\Enum
        ));
    }
}
