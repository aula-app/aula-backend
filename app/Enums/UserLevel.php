<?php

declare(strict_types=1);

namespace App\Enums;

enum UserLevel: int
{
    case Guest = 10;
    case User = 20;
    case Moderator = 30;
    case ModeratorPlus = 31;
    case SuperModerator = 40;
    case SuperModeratorPlus = 41;
    case Principal = 44;
    case PrincipalPlus = 45;
    case Admin = 50;
    case TechAdmin = 60;

    public function label(): string
    {
        return match ($this) {
            self::Guest => 'Guest',
            self::User => 'User',
            self::Moderator => 'Moderator',
            self::ModeratorPlus => 'Moderator+',
            self::SuperModerator => 'Super Moderator',
            self::SuperModeratorPlus => 'Super Moderator+',
            self::Principal => 'Principal',
            self::PrincipalPlus => 'Principal+',
            self::Admin => 'Admin',
            self::TechAdmin => 'Tech Admin',
        };
    }

    public static function labelFor(int $value): string
    {
        return self::tryFrom($value)?->label() ?? "Level {$value}";
    }
}
