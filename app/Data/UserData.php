<?php

declare(strict_types=1);

namespace App\Data;

use DateTimeImmutable;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Rule;

use App\Enums\UserLevel;
use App\Enums\UserStatus;

abstract class UserData extends Data
{
    // can't use MapInput or validation Attributes with abstract properties; must declare in final child class
    // can't specify abstract in constructor promotion
    abstract public int|null $id {
        // need hooked property for abstract; equals readonly
        get;
    }

    // we don't use Optional as it brings unnecessary complexity (e.g. it "infects" validation rules with a hard-to-shake `sometimes`, which overrides `required`)
    // instead we use nullable (which is not inferred as `sometimes`)
    abstract public string|null $hashId { get; }

    abstract public UserLevel|null $userLevel { get; }

    // Validation (#[Email]) of abstract must be done in child
    abstract public string|null $email { get; }

    abstract public string|null $aboutMe { get; }

    abstract public DateTimeImmutable|null $createdAt { get; }

    abstract public DateTimeImmutable|null $updatedAt { get; }

    public function __construct(
        int|null $id,

        string|null $hashId,

        #[MapInputName('displayname')]
        #[MapOutputName('displayname')]
        #[Max(400)]
        readonly public string $displayName,

        #[MapInputName('username')]
        #[MapOutputName('username')]
        #[Max(400)]
        readonly public string $userName,

        #[MapInputName('realname')]
        #[MapOutputName('realname')]
        #[Max(400)]
        readonly public string $realName,

        // N.B. truly nullable; can have value null
        string|null $email,

        UserLevel|null $userLevel,

        string|null $aboutMe,

        readonly public UserStatus $status,

        DateTimeImmutable|null $createdAt,

        DateTimeImmutable|null $updatedAt,
    ) {
        // abstract are unpromotable, need to be set up sans sugar
        $this->id = $id;
        $this->hashId = $hashId;
        $this->email = $email;
        $this->userLevel = $userLevel;
        $this->aboutMe = $aboutMe;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }
}
