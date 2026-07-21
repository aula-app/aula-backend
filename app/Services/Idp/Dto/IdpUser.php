<?php

declare(strict_types=1);

namespace App\Services\Idp\Dto;

/**
 * A person in an identity provider directory.
 *
 * A provider may distinguish "people" (configured to exist, may never
 * sign in) from "users" (can sign in via SSO). The two endpoints return
 * overlapping shapes: `/people/:id` adds `sourceSystemIdentifier`, `/users/:id`
 * adds `status`. Webhooks fire a single `person` event for both, so one DTO
 * covers both payloads and leaves the absent field null.
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
     * Combine two views of the same person, keeping whichever actually carries
     * each field.
     *
     * No single endpoint returns everything: names may come from a group's
     * member list, status and pseudonym from a user record, and an external
     * system id from somewhere else again. Group memberships are unioned rather
     * than replaced — seeing someone through one group does not put them out of
     * the others.
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
     * What to show in aula.
     *
     * Which name data an endpoint returns depends on the app's entitlements:
     * `/users` carries a `pseudonym` ("Denk Kapitän") and no `name`, while a
     * group's member list carries the real `name` and no pseudonym. Prefer the
     * real name and fall back to the pseudonym, so a person is never left
     * showing a generated username.
     */
    public function displayName(): string
    {
        $real = $this->name->display();

        return $real !== '' ? $real : (string) $this->pseudonym;
    }

    /**
     * The legal name, or null when the provider only gave us a pseudonym — a
     * pseudonym is not a real name and does not belong in `realname`.
     */
    public function realName(): ?string
    {
        $real = $this->name->real();

        return $real !== '' ? $real : null;
    }

    /**
     * the provider only documents ACTIVE. Treat a missing status as active so a
     * person the directory never gave a status to is not archived by accident.
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
