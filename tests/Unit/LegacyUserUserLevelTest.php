<?php

namespace Tests\Unit;

use App\Enums\UserLevel;
use App\Models\LegacyUser;
use Tests\TestCase;

class LegacyUserUserLevelTest extends TestCase
{
    public function test_it_casts_userlevel_to_enum_and_keeps_jwt_payload_integer(): void
    {
        $user = new LegacyUser();
        $user->userlevel = UserLevel::Moderator;

        $this->assertInstanceOf(UserLevel::class, $user->userlevel);
        $this->assertSame(UserLevel::Moderator, $user->userlevel);
        $this->assertSame(UserLevel::Moderator->value, $user->getJwtPayload()['userlevel']);
    }
}
