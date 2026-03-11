<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\TenantCreationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var TenantCreationService $service */
        $service = app(TenantCreationService::class);

        return $service->create(
            name: $data['name'],
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
