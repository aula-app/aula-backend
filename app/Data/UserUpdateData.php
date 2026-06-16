<?php

declare(strict_types=1);

namespace App\Data;

use App\Data\UserData;
use App\Enums\UserLevel;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Rule;

class UserUpdateData extends UserData
{
    #[Rule('missing')]
    public readonly int|null $id;

    #[Rule('missing')]
    #[MapInputName('hash_id')]
    public readonly string|null $hashId;

    #[Email]
    public readonly string $email;

    #[MapInputName('userlevel')]
    public readonly UserLevel $userLevel;

    #[MapInputName('about_me')]
    public readonly string $aboutMe;
}
