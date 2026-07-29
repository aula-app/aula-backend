<?php

declare(strict_types=1);

namespace App\Data\Room;

use DateTimeImmutable;
use App\Data\Room\AbstractRoomData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;

class DomainIdeaData extends AbstractIdeaData
{
    #[MapInputName('hash_id')]
    #[MapOutputName('public_id')]
    public readonly string $publicId;

    public readonly null|string $title;

    public readonly null|string $content;

    public readonly null|IdeaStatus $status;

    #[MapInputName('created')]
    #[MapOutputName('created_at')]
    public readonly null|DateTimeImmutable $createdAt;

    #[MapInputName('last_update')]
    #[MapOutputName('updated_at')]
    public readonly null|DateTimeImmutable $updatedAt;
}
