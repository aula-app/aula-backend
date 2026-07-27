<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class ChangePasswordData extends Data
{
    public function __construct(
        #[MapInputName('old_password')]
        public readonly string $currentPassword,

        #[MapInputName('password')]
        #[Min(12)]
        public readonly string $newPassword,
    ) {
    }
}
