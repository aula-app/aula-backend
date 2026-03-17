<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Services\TenantsService;
use App\UseCases\CreateTenantUseCase;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

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
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
