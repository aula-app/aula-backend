<?php

declare(strict_types=1);

namespace App\Data\User;

use DateTimeImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use App\Enums\UserLevel;
use App\Enums\UserStatus;

abstract class AbstractUserData extends Data
{
    /*
      - can't use MapInput or validation Attributes with abstract properties; must declare in final child class
      - can't specify abstract in constructor promotion
      - we don't use Optional as it brings unnecessary complexity (e.g. it "infects" validation rules with a hard-to-shake `sometimes`, which overrides `required`)
      - instead we use nullable (which is not inferred as `sometimes`)
    */
    abstract public null|string $publicId { get; }

    abstract public null|UserLevel $userLevel { get; }

    // Validation (#[Email]) of abstract must be done in child
    abstract public null|string $email { get; }

    abstract public null|string $aboutMe { get; }

    abstract public null|DateTimeImmutable $createdAt { get; }

    abstract public null|DateTimeImmutable $updatedAt { get; }

    public function __construct(
        null|string $publicId,
        #[MapName('displayname')]
        #[Max(400)]
        public readonly string $displayName,
        #[MapName('username')]
        #[Max(400)]
        public readonly string $userName,
        #[MapName('realname')]
        #[Max(400)]
        public readonly string $realName,
        public readonly UserStatus $status,

        // N.B. truly nullable; can have value null
        null|string $email,
        null|UserLevel $userLevel,
        null|string $aboutMe,
        null|DateTimeImmutable $createdAt,
        null|DateTimeImmutable $updatedAt,
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
