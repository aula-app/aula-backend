<?php

declare(strict_types=1);

namespace App\Data\User;

use DateTimeImmutable;
use App\Data\User\AbstractUserData;
use App\Enums\UserLevel;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;

class DomainUserData extends AbstractUserData
{
    // note: use (undocumented) #[Spatie\LaravelData\Attributes\Hidden] to remove properties from API output
    // this seems easier and cleaner than ::from($legacyUser)->except or Lazy

    // need to repeat abstract, including types, but types can be a subset
    // (see also NonInvariantPropertyType in psalm.xml)
    // all abstracts non-Optional in Model

    // different Input vs Output, see `created` below
    #[MapInputName('hash_id')]
    #[MapOutputName('public_id')]
    public readonly string $publicId;

    // N.b. true nullable (not only to signal optional)
    public readonly string|null $email;

    #[MapName('userlevel')]
    public readonly UserLevel $userLevel;

    #[MapName('about_me')]
    public readonly string $aboutMe;

    // Input+Output not synonymous here:
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
