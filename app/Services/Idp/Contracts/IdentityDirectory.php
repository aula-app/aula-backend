<?php

declare(strict_types=1);

namespace App\Services\Idp\Contracts;

use App\Services\Idp\Dto\IdpGroup;
use App\Services\Idp\Dto\IdpSchool;
use App\Services\Idp\Dto\IdpUser;

/**
 * Read access to an identity provider's directory.
 *
 * SchoolImport, TenantResolver and the Sync services all read through this and
 * none of them names a provider, so a new provider is an implementation of this
 * interface plus a WebhookAdapter, with no schema, route or sync change.
 *
 * An implementation owns its vendor's quirks: how many calls a listing takes,
 * which endpoint carries names, how roles are spelled.
 *
 * A missing entity is null or an empty list rather than an exception, since an
 * entity can be deleted between an event firing and it being handled. A genuine
 * failure throws DirectoryException.
 */
interface IdentityDirectory
{
    public function school(string $schoolId): ?IdpSchool;

    /**
     * Every group in the school, with members populated where the provider
     * exposes them.
     *
     * @return list<IdpGroup>
     */
    public function groups(string $schoolId): array;

    public function group(string $groupId): ?IdpGroup;

    /**
     * Everyone in the school, across whichever listings the provider exposes.
     *
     * @return list<IdpUser>
     */
    public function users(string $schoolId): array;

    public function user(string $userId): ?IdpUser;
}
