<?php

declare(strict_types=1);

namespace App\Services\Idp\Dto;

/**
 * A person in an identity provider directory, whether or not they can sign in.
 */
final readonly class IdpUser
{
    public const string ROLE_TEACHER = 'TEACHER';

    public const string ROLE_STUDENT = 'STUDENT';

    public const string STATUS_ACTIVE = 'ACTIVE';

    /**
     * @param  list<IdpGroupRef>  $groups
     */
    public function __construct(
        public string $id,
        public IdpUserName $name,
        public ?string $role = null,
        public ?string $status = null,
        public ?string $sourceSystemIdentifier = null,
        public array $groups = [],
        public ?string $pseudonym = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $name */
        $name = is_array($data['name'] ?? null) ? $data['name'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            name: IdpUserName::fromArray($name),
            role: is_string($data['role'] ?? null) ? $data['role'] : null,
            status: is_string($data['status'] ?? null) ? $data['status'] : null,
            sourceSystemIdentifier: is_string($data['sourceSystemIdentifier'] ?? null)
                ? $data['sourceSystemIdentifier']
                : null,
            groups: IdpGroupRef::listFromArray($data['groups'] ?? null),
            pseudonym: is_string($data['pseudonym'] ?? null) && $data['pseudonym'] !== ''
                ? $data['pseudonym']
                : null,
        );
    }

    /**
     * The name to write to `displayname`: the first name in use and the last
     * name, or the pseudonym when the directory returns no name.
     */
    public function displayName(): string
    {
        $name = $this->name->display();

        return $name !== '' ? $name : (string) $this->pseudonym;
    }

    /**
     * The legal name, or null when the provider returned a pseudonym only. A
     * pseudonym does not belong in `realname`.
     */
    public function realName(): ?string
    {
        $real = $this->name->real();

        return $real !== '' ? $real : null;
    }

    /**
     * STATUS_ACTIVE is the only documented value, so a missing status counts as
     * active and a directory user carrying none is not archived by accident.
     */
    public function isActive(): bool
    {
        return $this->status === null || $this->status === self::STATUS_ACTIVE;
    }

    /**
     * @return list<string>
     */
    public function groupIds(): array
    {
        return array_map(fn (IdpGroupRef $group): string => $group->id, $this->groups);
    }
}
