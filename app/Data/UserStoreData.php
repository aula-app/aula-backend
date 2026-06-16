<?php

declare(strict_types=1);

namespace App\Data;

use DateTimeImmutable;

use App\Data\UserData;
use App\Enums\UserLevel;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Email;

use Spatie\LaravelData\Attributes\Validation\Rule;

class UserStoreData extends UserData
{
    // Need to repeat abstract; types can be a subset.
    // But we can't "remove" the property (or set to abstract-only)
    // we can only block it, via 'missing' validation.
    // It can't be subset to null only, either.
    #[Rule('missing')]
    #[MapInputName('public_id')]
    public readonly string|null $publicId;

    #[Email]
    public readonly string|null $email;

    // unexpectedly, this works without #[WithCast]
    #[MapInputName('userlevel')]
    public readonly UserLevel|null $userLevel;

    #[MapInputName('about_me')]
    public readonly string|null $aboutMe;

    #[Rule('missing')]
    // cf. UserModelData, where Input/Output differ
    #[MapInputName('created_at')]
    public readonly DateTimeImmutable|null $createdAt;

    #[Rule('missing')]
    #[MapInputName('updated_at')]
    public readonly DateTimeImmutable|null $updatedAt;
}
