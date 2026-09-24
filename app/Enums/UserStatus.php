<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: int
{
    case Inactive = 0;
    case Active = 1;
    case Suspended = 2;
    case Archived = 3;

    public function label(): string
    {
        return match ($this) {
            self::Inactive => 'Inactive',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Archived => 'Archived'
        };
    }
}
