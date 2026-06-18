<?php

declare(strict_types=1);

namespace App\Data;

use DateTimeImmutable;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;

use App\Enums\UserLevel;
use App\Enums\UserStatus;

abstract class UserData extends Data
{
    /*
      - can't use MapInput or validation Attributes with abstract properties; must declare in final child class
      - can't specify abstract in constructor promotion
      - we don't use Optional as it brings unnecessary complexity (e.g. it "infects" validation rules with a hard-to-shake `sometimes`, which overrides `required`)
      - instead we use nullable (which is not inferred as `sometimes`)
    */
    abstract public string|null $publicId { get; }

    abstract public UserLevel|null $userLevel { get; }

    // Validation (#[Email]) of abstract must be done in child
    abstract public string|null $email { get; }

    abstract public string|null $aboutMe { get; }

    abstract public DateTimeImmutable|null $createdAt { get; }

    abstract public DateTimeImmutable|null $updatedAt { get; }

    public function __construct(
        string|null $publicId,

        #[MapName('displayname')]
        #[Max(400)]
        readonly public string $displayName,

        #[MapName('username')]
        #[Max(400)]
        readonly public string $userName,

        #[MapName('realname')]
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
        $this->publicId = $publicId;
        $this->email = $email;
        $this->userLevel = $userLevel;
        $this->aboutMe = $aboutMe;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }
}
