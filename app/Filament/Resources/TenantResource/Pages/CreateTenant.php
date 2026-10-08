<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Services\TenantsService;
use App\UseCases\CreateTenantUseCase;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends CreateRecord<\App\Models\Tenant>
 */
class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    public function mount(): void
    {
        parent::mount();
        $this->data['instance_code'] = app(TenantsService::class)->generateUniqueInstanceCode();
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->requiresConfirmation()
            ->modalHeading('Confirm instance code')
            ->modalDescription(fn () => "The tenant will be created with instance code: {$this->data['instance_code']}")
            ->modalSubmitActionLabel('Confirm and create');
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateTenantUseCase::class)->execute(
            name: $data['name'],
            instanceCode: $data['instance_code'],
            admin1Username: $data['admin1_username'],
            admin1FullName: $data['admin1_name'] ?? $data['admin1_username'],
            admin1Email: $data['admin1_email'],
            admin2Username: $data['admin2_username'],
            admin2FullName: $data['admin2_name'] ?? $data['admin2_username'],
            admin2Email: $data['admin2_email'],
            apiBaseUrl: $this->optionalString($data, 'api_base_url'),
            isNamePublic: $this->optionalBool($data, 'is_name_public'),
            contactInfo: $this->optionalString($data, 'contact_info'),
            schoolTypeId: isset($data['school_type_id']) ? (int) $data['school_type_id'] : null,
            ssoEnabled: $this->optionalBool($data, 'sso_enabled'),
            ssoProvider: $this->optionalString($data, 'sso_provider'),
            ssoForceLogout: $this->optionalBool($data, 'sso_force_logout'),
            ssoRequired: $this->optionalBool($data, 'sso_required'),
            ssoRequireEmailVerified: $this->optionalBool($data, 'sso_require_email_verified'),
            idpMigrationStatus: $this->optionalString($data, 'idp_migration_status'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function optionalBool(array $data, string $key): ?bool
    {
        return isset($data[$key]) ? (bool) $data[$key] : null;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
