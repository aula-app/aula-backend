<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class PostLoginData extends Data
{
    public function __construct(
        public readonly string $username,
        public readonly string $password,
    ) {
    }
}
