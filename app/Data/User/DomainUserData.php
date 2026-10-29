<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Enums\RoomUserLevel;
use DateTimeImmutable;
use App\Data\User\AbstractUserData;
use App\Enums\UserLevel;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use App\Relations\RoomUser;
use Illuminate\Database\Eloquent\Relations\Pivot;

class DomainUserData extends AbstractUserData
{
    // note: use (undocumented) #[Spatie\LaravelData\Attributes\Hidden] to remove properties from API output
    // this seems easier and cleaner than ::from($legacyUser)->except or Lazy

    // need to repeat abstract, including types, but types can be a subset
    // (see also NonInvariantPropertyType in psalm.xml)
    // all abstracts non-Optional in Model

    // different Input vs Output, see `created` below
    #[MapInputName('hash_id')]
    public readonly string $publicId;

    #[MapInputName('displayname')]
    public readonly string|null $displayName;

    #[MapInputName('username')]
    public readonly string $userName;

    #[MapInputName('realname')]
    public readonly string|null $realName;

    // N.b. true nullable (not only to signal optional)
    public readonly string|null $email;

    #[MapInputName('userlevel')]
    // LegacyUser's fields are basically all NULLable, including userlevel, about_me and created_at (see below).
    // TODO: To work with legacy data, we need to allow null here, or, have sane defaults?
    public readonly UserLevel|null $userLevel;

    #[MapInputName('about_me')]
    public readonly string|null $aboutMe;

    // Input+Output not synonymous here:
    // Input: from legacyUser
    #[MapInputName('created')]
    // Output: to API, as JSON Resource
    // #[MapOutputName('created_at')]
    // cf. UserStore/UpdateData only have Input
    public readonly DateTimeImmutable $createdAt;

    #[MapInputName('last_update')]
    // unlike created, this can still be null (at creation)
    public readonly DateTimeImmutable|null $updatedAt;

    #[Hidden]
    public readonly null|Pivot $pivot;
}
