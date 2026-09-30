<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Tenant\DomainTenantPublicData;
use App\Models\Tenant;
use Illuminate\Routing\Controller;
use Spatie\LaravelData\DataCollection;

class TenantController extends Controller
{
    /**
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @return DataCollection<array-key, DomainTenantPublicData>
     */
    public function indexPublic(): DataCollection
    {
        return DomainTenantPublicData::collect(
            Tenant::where('is_name_public', true)->get(),
            DataCollection::class
        );
    }
}
