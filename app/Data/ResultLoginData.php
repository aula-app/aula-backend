<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class ResultLoginData extends Data
{
    public function __construct(
        public readonly bool $success,
    ) {
    }
}
