<?php

declare(strict_types=1);

namespace App\Services\Idp\Dto;

/**
 * A group as it appears nested inside a person or school listing: id and name
 * only, without the member list.
 */
final readonly class IdpGroupRef
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $status = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            status: is_string($data['status'] ?? null) ? $data['status'] : null,
        );
    }

    /**
     * @param  mixed  $groups
     * @return list<self>
     */
    public static function listFromArray($groups): array
    {
        if (! is_array($groups)) {
            return [];
        }

        $refs = [];

        foreach ($groups as $group) {
            if (is_array($group) && ! empty($group['id'])) {
                $refs[] = self::fromArray($group);
            }
        }

        return $refs;
    }
}
