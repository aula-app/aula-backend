<?php

namespace App\Data;

use App\Data\UserData;
use App\Enums\UserLevel;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Email;

class UserUpdateData extends UserData
{
    public readonly int|Optional $id;
    public readonly string|Optional $hashId;

    #[Email]
    public readonly string $email;

    #[MapInputName('userlevel')]
    public readonly UserLevel $userLevel;

    #[MapInputName('about_me')]
    public readonly string $aboutMe;
}
