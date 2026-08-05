<?php

declare(strict_types=1);

namespace App\Data\Idea\Requests;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class ListIdeasMineData extends Data
{
    function __construct(
        // TODO: validate format here?
        #[MapInputName('room')]
        public readonly null|string $roomPublicId,
        // TODO enum
        #[MapInputName('phase')]
        public readonly null|int $phaseId,
    ) {
    }
}
