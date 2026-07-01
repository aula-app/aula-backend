<?php

declare(strict_types=1);

namespace App\Data\User\Requests;

use DateTimeImmutable;
use App\Data\User\AbstractUserData;
use App\Enums\UserLevel;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Rule;

class StoreUserData extends AbstractUserData
{
    #[Email]
    public readonly null|string $email;

    // unexpectedly, this works without #[WithCast]
    #[MapInputName('userlevel')]
    public readonly null|UserLevel $userLevel;

    #[MapInputName('about_me')]
    public readonly null|string $aboutMe;

    // ======================================================
    // Need to repeat abstract; types can be a subset.
    // But we can't "remove" the property (or set to abstract-only)
    // we can only block it, via 'missing' validation.
    // It can't be subset to null only, either.
    // =====================================================
    #[Rule('missing')]
    #[MapInputName('public_id')]
    public readonly null|string $publicId;

    #[Rule('missing')]
    // cf. UserModelData, where Input/Output differ
    #[MapInputName('created_at')]
    public readonly null|DateTimeImmutable $createdAt;

    #[Rule('missing')]
    #[MapInputName('updated_at')]
    public readonly null|DateTimeImmutable $updatedAt;
}
