<?php

namespace Tests\Unit;

use App\Data\UserStoreData;
use App\Data\UserUpdateData;
use Tests\TestCase;

class UserDataTest extends TestCase
{
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
