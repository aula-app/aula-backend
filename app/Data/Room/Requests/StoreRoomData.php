<?php

declare(strict_types=1);

namespace App\Data\Room\Requests;

use DateTimeImmutable;
use App\Data\Room\AbstractRoomData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Rule;

class StoreRoomData extends AbstractRoomData
{
    #[Rule('missing')]
    // TODO #[MapInputName('public_id')]
    // TODO see if this validates (i.e. ?publicId=foo not allowed)
    public readonly null|string $publicId;

    #[MapInputName('room_name')]
    #[Max(1024)]
    public readonly null|string $name;

    // TODO create RoomStatus
    public readonly null|int $status;

    #[MapInputName('description_public')]
    public readonly null|string $descriptionPublic;

    #[MapInputName('description_internal')]
    public readonly null|string $descriptionInternal;

    #[MapInputName('phase_duration_1')]
    #[Min(1)]
    public readonly null|int $phaseDuration1;

    #[MapInputName('phase_duration_3')]
    #[Min(1)]
    public readonly null|int $phaseDuration3;

    #[Rule('missing')]
    public readonly null|DateTimeImmutable $createdAt;

    #[Rule('missing')]
    public readonly null|DateTimeImmutable $updatedAt;
}
