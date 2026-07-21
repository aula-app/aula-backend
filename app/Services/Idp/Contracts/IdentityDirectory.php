<?php

declare(strict_types=1);

namespace App\Services\Idp\Contracts;

use App\Services\Idp\Dto\IdpGroup;
use App\Services\Idp\Dto\IdpSchool;
use App\Services\Idp\Dto\IdpUser;

/**
 * Read access to an identity provider's directory.
 *
 * Everything aula does with a synced school — import, tenant resolution,
 * webhook convergence — goes through this. Nothing above it knows which
 * provider is behind it, so adding one means implementing this interface and
 * a WebhookAdapter, with no schema, route or sync changes.
 *
 * Implementations own their vendor's quirks: how many calls a listing really
 * takes, which endpoint carries names, how roles are spelled. Callers see one
 * shape.
 *
 * A missing entity is null or an empty list, not an exception — entities
 * disappear between an event firing and it being handled. Genuine failures
 * throw DirectoryException.
 */
interface IdentityDirectory
{
    public function school(string $schoolId): ?IdpSchool;

    /**
     * Every group in the school, with members populated where the provider can.
     *
     * @return list<IdpGroup>
     */
    public function groups(string $schoolId): array;

    public function group(string $groupId): ?IdpGroup;

    /**
     * Everyone in the school, however the provider chooses to expose them.
     *
     * @return list<IdpUser>
     */
    public function users(string $schoolId): array;

    public function user(string $userId): ?IdpUser;
}
