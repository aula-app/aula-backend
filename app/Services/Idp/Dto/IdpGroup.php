<?php

declare(strict_types=1);

namespace App\Services\Idp\Dto;

final readonly class IdpGroup
{
    public const string STATUS_ACTIVE = 'ACTIVE';

    /**
     * @param  list<IdpUser>  $members
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $status = null,
        public array $members = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $members = [];

        if (is_array($data['members'] ?? null)) {
            foreach ($data['members'] as $member) {
                if (is_array($member) && ! empty($member['id'])) {
                    $members[] = IdpUser::fromArray($member);
                }
            }
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            status: is_string($data['status'] ?? null) ? $data['status'] : null,
            members: $members,
        );
    }

    public function isActive(): bool
    {
        return $this->status === null || $this->status === self::STATUS_ACTIVE;
    }

    /**
     * @return list<string>
     */
    public function memberIds(): array
    {
        return array_map(fn (IdpUser $member): string => $member->id, $this->members);
    }
}
