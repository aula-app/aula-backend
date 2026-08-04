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

class DomainRoomData extends AbstractRoomData
{
    #[MapInputName('hash_id')]
    public readonly string $publicId;

    #[MapInputName('room_name')]
    public readonly null|string $name;

    public readonly null|int $status;

    #[MapInputName('description_public')]
    public readonly null|string $descriptionPublic;

    // TODO: when/for whom is this visible?
    #[MapInputName('description_internal')]
    public readonly null|string $descriptionInternal;

    #[MapInputName('phase_duration_1')]
    public readonly null|int $phaseDuration1;

    #[MapInputName('phase_duration_3')]
    public readonly null|int $phaseDuration3;

    #[MapInputName('created')]
    public readonly null|DateTimeImmutable $createdAt;

    #[MapInputName('last_update')]
    public readonly null|DateTimeImmutable $updatedAt;
}
