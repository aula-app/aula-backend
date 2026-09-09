<?php

declare(strict_types=1);

namespace App\Data\Room\Requests;

use DateTimeImmutable;
use App\Data\Room\AbstractRoomData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Rule;

class UpdateRoomData extends AbstractRoomData
{
    #[Rule('missing')]
    public readonly null|string $publicId;

    // TODO check if required

    #[Max(1024)]
    public readonly string $name;

    public readonly int $status;

    public readonly string $descriptionPublic;

    public readonly string $descriptionInternal;

    #[Min(1)]
    public readonly int $phaseDuration1;

    #[Min(1)]
    public readonly int $phaseDuration3;

    #[Rule('missing')]
    public readonly DateTimeImmutable $createdAt;

    #[Rule('missing')]
    public readonly DateTimeImmutable $updatedAt;
}
