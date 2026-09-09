<?php

declare(strict_types=1);

namespace App\Data\Room;

use DateTimeImmutable;
use Spatie\LaravelData\Data;

abstract class AbstractRoomData extends Data
{
    abstract public null|string $publicId { get; }
    abstract public null|string $name { get; }
    // TODO: make RoomStatus
    abstract public null|int $status { get; }
    abstract public null|string $descriptionPublic { get; }
    abstract public null|string $descriptionInternal { get; }
    abstract public null|int $phaseDuration1 { get; }
    abstract public null|int $phaseDuration3 { get; }
    abstract public null|DateTimeImmutable $createdAt { get; }
    abstract public null|DateTimeImmutable $updatedAt { get; }

    public function __construct(
        null|string $publicId,
        null|string $name,
        null|int $status,
        null|string $descriptionPublic,
        null|string $descriptionInternal,
        null|int $phaseDuration1,
        null|int $phaseDuration3,
        null|DateTimeImmutable $createdAt,
        null|DateTimeImmutable $updatedAt,
    ) {
        $this->publicId = $publicId;
        $this->name = $name;
        $this->status = $status;
        $this->descriptionPublic = $descriptionPublic;
        $this->descriptionInternal = $descriptionInternal;
        $this->phaseDuration1 = $phaseDuration1;
        $this->phaseDuration3 = $phaseDuration3;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }
}
