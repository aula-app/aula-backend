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

    #[MapInputName('room_name')]
    #[Max(1024)]
    public readonly string $name;

    public readonly int $status;

    #[MapInputName('description_public')]
    public readonly string $descriptionPublic;

    #[MapInputName('description_internal')]
    public readonly string $descriptionInternal;

    #[MapInputName('phase_duration_1')]
    #[Min(1)]
    public readonly int $phaseDuration1;

    #[MapInputName('phase_duration_3')]
    #[Min(1)]
    public readonly int $phaseDuration3;

    #[Rule('missing')]
    public readonly DateTimeImmutable $createdAt;

    #[Rule('missing')]
    public readonly DateTimeImmutable $updatedAt;
}
