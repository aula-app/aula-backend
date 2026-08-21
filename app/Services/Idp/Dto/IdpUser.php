<?php

declare(strict_types=1);

namespace App\Services\Idp\Dto;

/**
 * A person in an identity provider directory.
 *
 * A provider can distinguish people, configured to exist and possibly never
 * signing in, from users, which can sign in. The endpoints overlap:
 * `/people/:id` adds `sourceSystemIdentifier`, `/users/:id` adds `status`.
 * Webhooks fire one `person` event for both, so this DTO covers both payloads
 * and leaves the absent field null.
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
     * Combine two views of one directory user, keeping whichever carries each
     * field.
     *
     * No single endpoint returns everything: `name` can come from a group
     * member list, `status` and `pseudonym` from a user record, and
     * `sourceSystemIdentifier` from a person record. Group refs are unioned
     * rather than replaced, so a view through one group does not drop the rest.
     */
    public function mergedWith(self $other): self
    {
        $groups = $this->groups;
        $seen = array_map(fn (IdpGroupRef $g): string => $g->id, $groups);

        foreach ($other->groups as $group) {
            if (! in_array($group->id, $seen, true)) {
                $groups[] = $group;
                $seen[] = $group->id;
            }
        }

        return new self(
            id: $this->id,
            name: $this->name->real() !== '' ? $this->name : $other->name,
            role: $this->role ?? $other->role,
            status: $this->status ?? $other->status,
            sourceSystemIdentifier: $this->sourceSystemIdentifier ?? $other->sourceSystemIdentifier,
            groups: $groups,
            pseudonym: $this->pseudonym ?? $other->pseudonym,
        );
    }

    /**
     * The name to write to `displayname`.
     *
     * Which name fields an endpoint returns depends on the app's entitlements:
     * `/users` carries a `pseudonym` ("Denk Kapitän") and no `name`, a group
     * member list carries `name` and no pseudonym. The real name wins, with the
     * pseudonym as fallback so no account is left showing a generated username.
     */
    public function displayName(): string
    {
        $real = $this->name->display();

        return $real !== '' ? $real : (string) $this->pseudonym;
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
