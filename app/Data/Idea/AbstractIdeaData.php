<?php

declare(strict_types=1);

namespace App\Data\Idea;

use App\Data\Room\DomainRoomData;
use App\Enums\IdeaStatus;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use DateTimeImmutable;
use Spatie\LaravelData\Attributes\Computed;

abstract class AbstractIdeaData extends Data
{
    abstract public null|string $publicId { get; }
    abstract public null|string $title { get; }
    // TODO: make IdeaStatus
    abstract public null|string $content { get; }
    abstract public null|IdeaStatus $status { get; }
    abstract public null|DomainRoomData $room { get; }
    abstract public null|string $roomId { get; }
    abstract public null|DateTimeImmutable $createdAt { get; }
    abstract public null|DateTimeImmutable $updatedAt { get; }

    #[Computed]
    public readonly null|string $roomPublicId;

    public function __construct(
        null|string $publicId,
        null|string $title,
        null|string $content,
        null|IdeaStatus $status,
        null|DomainRoomData $room,
        null|string $roomId,
        null|DateTimeImmutable $createdAt,
        null|DateTimeImmutable $updatedAt,
    ) {
        $this->publicId = $publicId;
        $this->title = $title;
        $this->content = $content;
        $this->status = $status;
        $this->room = $room;
        $this->roomId = $roomId;
        $this->roomPublicId = $this->room->publicId;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }
}
