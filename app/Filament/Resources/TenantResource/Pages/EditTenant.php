<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * @extends EditRecord<\App\Models\Tenant>
 */
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
     * Mark existing usernames as manually set so editing the email does not
     * silently overwrite them. The Hidden field defaults cannot be relied on
     * here because they live in a section declared before the username fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['admin1_username_manual'] = !empty($data['admin1_username'] ?? null);
        $data['admin2_username_manual'] = !empty($data['admin2_username'] ?? null);

        return $data;
    }

    /**
     * Only allow editing of safe fields. instance_code and jwt_key are never
     * overwritten through the panel — they are displayed as read-only.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['instance_code'], $data['jwt_key']);

        return $data;
    }
}
