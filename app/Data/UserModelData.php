<?php

declare(strict_types=1);

namespace App\Data;

use DateTimeImmutable;

use App\Data\UserData;
use App\Enums\UserLevel;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\Validation\Rule;

class UserModelData extends UserData
{
    // need to repeat abstract, including types, but types can be a subset
    // (see also NonInvariantPropertyType in psalm.xml)
    // all abstracts non-Optional in Model
    public readonly int $id;

    // Map both Input + Output here for de/serialization
    #[MapName('hash_id')]
    public readonly string $hashId;

    // N.b. true nullable (not only to signal optional)
    public readonly string|null $email;

    #[MapName('userlevel')]
    public readonly UserLevel $userLevel;

    #[MapName('about_me')]
    public readonly string $aboutMe;

    // Input: from legacyUser
    #[MapInputName('created')]
    // Output: to API, as JSON Resource
    #[MapOutputName('created_at')]
    // cf. UserStore/UpdateData only have Input
    public readonly DateTimeImmutable $createdAt;

    #[MapInputName('last_update')]
    #[MapOutputName('updated_at')]
    // unlike created, this can still be null (at creation)
    public readonly DateTimeImmutable|null $updatedAt;
}

