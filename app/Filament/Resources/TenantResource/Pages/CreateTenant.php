<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Services\TenantsService;
use App\UseCases\CreateTenantUseCase;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

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
        $tenant = app(CreateTenantUseCase::class)->execute(
            name: $data['name'],
            instanceCode: $data['instance_code'],
            admin1Username: $data['admin1_username'],
            admin1FullName: $data['admin1_name'] ?? $data['admin1_username'],
            admin1Email: $data['admin1_email'],
            admin2Username: $data['admin2_username'],
            admin2FullName: $data['admin2_name'] ?? $data['admin2_username'],
            admin2Email: $data['admin2_email'],
        );

        // The use case takes the identity fields only; the rest of the form
        // (SSO, contact, school type) is saved here.
        $tenant->update(Arr::except($data, [
            'name', 'instance_code', 'admin1_username_manual', 'admin2_username_manual',
            'admin1_name', 'admin1_username', 'admin1_email',
            'admin2_name', 'admin2_username', 'admin2_email',
        ]));

        return $tenant;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
