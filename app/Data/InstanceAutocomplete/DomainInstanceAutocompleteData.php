<?php

declare(strict_types=1);

namespace App\Data\InstanceAutocomplete;

use Spatie\LaravelData\Data;

class DomainInstanceAutocompleteData extends Data
{
    public function __construct(
        public readonly string $instance_code,
        public readonly string $name
    ) {
    }
}
