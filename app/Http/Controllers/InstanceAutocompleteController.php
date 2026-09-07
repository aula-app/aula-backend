<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\InstanceAutocomplete\DomainInstanceAutocompleteData;
use App\Models\Tenant;
use Spatie\LaravelData\DataCollection;

class InstanceAutocompleteController extends Controller {

    /**
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @return DataCollection<array-key, DomainInstanceAutocompleteData>
     */
    public function index(): DataCollection
    {
        return DomainInstanceAutocompleteData::collect(
            Tenant::where('allow_search_by_name', true)->get(),
            DataCollection::class
        );
    }
}
