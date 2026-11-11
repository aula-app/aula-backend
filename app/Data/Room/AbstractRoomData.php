<?php

declare(strict_types=1);

namespace App\Data\Room;

use App\Enums\RoomStatus;
use Spatie\LaravelData\Data;
use DateTimeImmutable;

abstract class AbstractRoomData extends Data
{
    abstract public null|string $publicId { get; }
    abstract public null|string $name { get; }
    abstract public null|RoomStatus $status { get; }
    abstract public null|string $descriptionPublic { get; }
    abstract public null|string $descriptionInternal { get; }
    abstract public null|int $phaseDurationVoting { get; }
    abstract public null|int $phaseDurationDiscussion { get; }
    abstract public null|DateTimeImmutable $createdAt { get; }
    abstract public null|DateTimeImmutable $updatedAt { get; }

    public function __construct(
        null|string $publicId,
        null|string $name,
        null|RoomStatus $status,
        null|string $descriptionPublic,
        null|string $descriptionInternal,
        null|int $phaseDurationVoting,
        null|int $phaseDurationDiscussion,
        null|DateTimeImmutable $createdAt,
        null|DateTimeImmutable $updatedAt,
    ) {
        $this->publicId = $publicId;
        $this->name = $name;
        $this->status = $status;
        $this->descriptionPublic = $descriptionPublic;
        $this->descriptionInternal = $descriptionInternal;
        $this->phaseDurationVoting = $phaseDurationVoting;
        $this->phaseDurationDiscussion = $phaseDurationDiscussion;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }
}
