<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Filament\Resources\TenantResource;
use App\Models\Tenant;
use Filament\Actions\Action;

/**
 * Links a tenant to its temporary-passwords page.
 */
class TemporaryPasswordsAction
{
    public const string NAME = 'temporaryPasswords';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('Temporary passwords')
            ->icon('heroicon-o-key')
            ->color('warning')
            // Password login is refused for an SSO-only tenant.
            ->visible(fn (Tenant $record): bool => ! $record->sso_required)
            ->url(fn (Tenant $record): string => TenantResource::getUrl('temp-passwords', ['record' => $record]));
    }
}
