<?php

declare(strict_types=1);

namespace App\Data;

use DateTimeImmutable;

use App\Data\UserData;
use App\Enums\UserLevel;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Rule;

class UserUpdateData extends UserData
{
    #[Rule('missing')]
    #[MapInputName('public_id')]
    public readonly string|null $publicId;

    #[Email]
    public readonly string $email;

    #[MapInputName('userlevel')]
    public readonly UserLevel $userLevel;

    #[MapInputName('about_me')]
    public readonly string $aboutMe;

    #[Rule('missing')]
    #[MapInputName('created_at')]
    public readonly DateTimeImmutable|null $createdAt;

    #[Rule('missing')]
    #[MapInputName('updated_at')]
    public readonly DateTimeImmutable|null $updatedAt;
}
