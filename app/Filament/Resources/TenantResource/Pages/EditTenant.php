<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Tenant updated';
    }

    /**
     * Only allow editing of safe fields. instance_code and jwt_key are never
     * overwritten through the panel — they are displayed as read-only.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['instance_code'], $data['jwt_key']);

        return $data;
    }
}
