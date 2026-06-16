<?php

declare(strict_types=1);

namespace App\Data;

use App\Data\UserData;
use App\Enums\UserLevel;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Email;

use Spatie\LaravelData\Attributes\Validation\Rule;

class UserStoreData extends UserData
{
    // need to repeat abstract, types can be a subset,
    // but we can't "remove" the property (or set to abstract-only)
    // it can't be null only, either
    #[Rule('missing')]
    public readonly int|null $id;

    #[Rule('missing')]
    #[MapInputName('hash_id')]
    public readonly string|null $hashId;

    #[Email]
    public readonly string|null $email;

    // unexpectedly, this works without #[WithCast]
    #[MapInputName('userlevel')]
    public readonly UserLevel|null $userLevel;

    #[MapInputName('about_me')]
    public readonly string|null $aboutMe;
}
