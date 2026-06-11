<?php

declare(strict_types=1);

namespace App\Data;

use App\Data\UserData;
use App\Enums\UserLevel;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Email;

class UserStoreData extends UserData
{
    // need to repeat abstract, types can be a subset,
    // but we can't "remove" the property (or set to abstract-only)
    // it can't be Optional only, either
    public readonly int|Optional $id;
    public readonly string|Optional $hashId;

    // have to repeat validation
    #[Email]
    public readonly string|Optional $email;

    #[MapInputName('userlevel')]
    public readonly UserLevel|Optional $userLevel;

    #[MapInputName('about_me')]
    public readonly string|Optional $aboutMe;
}
