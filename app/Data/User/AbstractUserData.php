<?php

declare(strict_types=1);

namespace App\Data\User;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Relations\RoomUser;

abstract class AbstractUserData extends Data
{
    /*
      - can't use MapInput or validation Attributes with abstract properties; must declare in final child class
      - can't specify abstract in constructor promotion
      - we don't use Optional as it brings unnecessary complexity (e.g. it "infects" validation rules with a hard-to-shake `sometimes`, which overrides `required`)
      - instead we use nullable (which is not inferred as `sometimes`)
    */
    abstract public null|string $publicId { get; }

    abstract public null|string $displayName { get; }
    abstract public string $userName { get; }
    abstract public null|string $realName { get; }

    abstract public null|UserLevel $userLevel { get; }

    // Validation (#[Email]) of abstract must be done in child
    abstract public null|string $email { get; }

    abstract public null|string $aboutMe { get; }

    abstract public null|DateTimeImmutable $createdAt { get; }

    abstract public null|DateTimeImmutable $updatedAt { get; }

    /** @var null|Collection<int, RoomUser> */
    abstract public null|Collection $rooms { get; }

    /**
     * @param null|Collection<int, RoomUser> $rooms
     */
    public function __construct(
        null|string $publicId,
        null|string $displayName,
        string $userName,
        null|string $realName,
        public readonly UserStatus $status,

        // N.B. truly nullable; can have value null
        null|string $email,
        null|UserLevel $userLevel,
        null|string $aboutMe,
        null|DateTimeImmutable $createdAt,
        null|DateTimeImmutable $updatedAt,

        null|Collection $rooms,
    ) {
        // abstract are unpromotable, need to be set up sans sugar
        $this->publicId = $publicId;
        $this->displayName = $displayName;
        $this->userName = $userName;
        $this->realName = $realName;
        $this->email = $email;
        $this->userLevel = $userLevel;
        $this->aboutMe = $aboutMe;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->rooms = $rooms;
    }
}
