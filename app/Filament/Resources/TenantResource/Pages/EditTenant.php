<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Models\Tenant;
use Filament\Actions;
use Filament\Notifications\Notification;
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
            Actions\Action::make('releaseIdpSchool')
                ->label('Release from IdP school')
                ->icon('heroicon-o-link-slash')
                ->color('warning')
                ->visible(fn (Tenant $record): bool => $record->idp_school_id !== null)
                ->requiresConfirmation()
                ->modalDescription(fn (Tenant $record): string => "Unlinks school {$record->idp_school_id} from this tenant so it can be connected to another tenant. Imported users and rooms are kept but their links to the provider are removed.")
                ->action(function (Tenant $record): void {
                    $record->update(['idp_school_id' => null]);
                    Notification::make()->title('Tenant released from IdP school')->success()->send();
                }),
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
        // these are just ephemeral fields for the filament page
        unset($data['admin1_username_manual'], $data['admin2_username_manual']);

        return $data;
    }
}
