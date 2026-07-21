<?php

declare(strict_types=1);

namespace App\Services\Idp\Dto;

final readonly class IdpSchool
{
    /**
     * @param  array<string, mixed>  $address
     * @param  array<string, mixed>  $location
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $address = [],
        public array $location = [],
        public ?string $officialId = null,
        public ?string $schoolingLevel = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            address: is_array($data['address'] ?? null) ? $data['address'] : [],
            location: is_array($data['location'] ?? null) ? $data['location'] : [],
            officialId: is_string($data['officialId'] ?? null) ? $data['officialId'] : null,
            // Webhooks list `schooling_level` among the properties a school event
            // can report, but the documented school schema has no matching field.
            // Read both spellings so we log whichever one actually turns up.
            schoolingLevel: is_string($data['schoolingLevel'] ?? null)
                ? $data['schoolingLevel']
                : (is_string($data['schooling_level'] ?? null) ? $data['schooling_level'] : null),
        );
    }
}
