<?php

declare(strict_types=1);

namespace App\Data\Room;

use App\Data\Room\AbstractRoomData;
use App\Enums\RoomStatus;
use Spatie\LaravelData\Attributes\MapInputName;
use DateTimeImmutable;

class DomainRoomData extends AbstractRoomData
{
    #[MapInputName('hash_id')]
    public readonly string $publicId;

    #[MapInputName('room_name')]
    public readonly null|string $name;

    public readonly null|RoomStatus $status;

    #[MapInputName('description_public')]
    public readonly null|string $descriptionPublic;

    #[MapInputName('description_internal')]
    public readonly null|string $descriptionInternal;

    #[MapInputName('phase_duration_1')]
    public readonly null|int $phaseDurationVoting;

    #[MapInputName('phase_duration_3')]
    public readonly null|int $phaseDurationDiscussion;

    #[MapInputName('created')]
    public readonly null|DateTimeImmutable $createdAt;

    #[MapInputName('last_update')]
    public readonly null|DateTimeImmutable $updatedAt;
}
