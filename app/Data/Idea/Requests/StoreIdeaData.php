<?php

declare(strict_types=1);

namespace App\Data\Idea\Requests;

use App\Data\Idea\AbstractIdeaData;
use App\Enums\IdeaStatus;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use DateTimeImmutable;

class StoreIdeaData extends AbstractIdeaData
{
    #[Rule('missing')]
    public readonly null|string $publicId;

    public readonly null|string $title;

    public readonly null|string $content;

    public readonly null|IdeaStatus $status;

    #[Rule('missing')]
    public readonly null|DateTimeImmutable $createdAt;

    #[Rule('missing')]
    public readonly null|DateTimeImmutable $updatedAt;
}
