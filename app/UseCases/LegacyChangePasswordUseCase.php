<?php

declare(strict_types=1);

namespace App\UseCases;

use App\Data\ChangePasswordData;
use App\Models\LegacyUser;
use App\Services\LegacyJwtService;
use App\Data\PostLoginData;

class LegacyChangePasswordUseCase
{
    public function execute(LegacyUser $user, ChangePasswordData $changePasswordData): array
    {
        // check if requestPasswordCurrent matches passwordCurrent
        // if temp_pw
        //   hash_equals(jwt.temp_pw, $requestPasswordCurrent)
        //   or just check $user-pw? or both?
        // else
        //   password_verify($requestPasswordCurrent, $user->pw)
        // when check fails, return success=>false

        // (if temp_pw)
        //   or $user->unsetTempPW

        // $user->setUserPW($user->id, $requestPasswordNew)
        // if was temp_pw
        //   generate new jwt for $user,
        //   return ['JWT' => ...]
        // else
        //   return just success=>true
    }

}
