<?php

declare(strict_types=1);

namespace App\Data\Room\Requests;

use App\Data\Room\AbstractRoomData;
use App\Enums\RoomStatus;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Rule;
use DateTimeImmutable;

class UpdateRoomData extends AbstractRoomData
{
    #[Rule('missing')]
    public readonly null|string $publicId;

    #[Max(1024)]
    public readonly string $name;

    public readonly RoomStatus $status;

    public readonly string $descriptionPublic;

    public readonly string $descriptionInternal;

    #[Min(1)]
    public readonly int $phaseDurationVoting;

    #[Min(1)]
    public readonly int $phaseDurationDiscussion;

    #[Rule('missing')]
    public readonly null|DateTimeImmutable $createdAt;

    #[Rule('missing')]
    public readonly null|DateTimeImmutable $updatedAt;
}
