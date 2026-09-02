<?php

declare(strict_types=1);

namespace App\Data\User\Requests;

use DateTimeImmutable;
use App\Data\User\AbstractUserData;
use App\Enums\UserLevel;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Rule;
use App\Relations\RoomUser;

class UpdateUserData extends AbstractUserData
{
    #[Max(400)]
    public readonly string $displayName;
    #[Max(400)]
    public readonly string $userName;
    #[Max(400)]
    public readonly string $realName;

    #[Email]
    public readonly string $email;

    public readonly UserLevel $userLevel;

    public readonly string $aboutMe;

    // ======================================================
    // Need to repeat abstract; types can be a subset.
    // But we can't "remove" the property (or set to abstract-only)
    // we can only block it, via 'missing' validation.
    // It can't be subset to null only, either.
    // =====================================================
    #[Rule('missing')]
    public readonly null|string $publicId;

    #[Rule('missing')]
    public readonly null|DateTimeImmutable $createdAt;

    #[Rule('missing')]
    public readonly null|DateTimeImmutable $updatedAt;

    #[Hidden]
    public readonly null $pivot;
}
