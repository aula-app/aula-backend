<?php

declare(strict_types=1);

namespace App\Services\Idp;

use App\Models\IdpDirectoryEntry;
use App\Models\Tenant;
use App\Services\Idp\Dto\IdpEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Answers which tenant a directory entity belongs to.
 *
 * An IdpEvent::ENTITY_SCHOOL maps straight onto `tenants.idp_school_id`. User
 * and group events carry no school identifier, so nothing in the payload routes
 * them, and those take two steps:
 *
 *   1. Look the id up in `idp_directory`, which SchoolImport and every scan
 *      below populate.
 *   2. On a miss, scan that provider's tenants through IdentityDirectory. Every
 *      id seen on the way is indexed, so one scan warms a whole school and the
 *      next event for any of its members is a single lookup.
 *
 * An id that resolves to nothing is cached for UNRESOLVABLE_TTL_SECONDS, so a
 * school this installation does not host cannot make each of its events start a
 * fresh scan.
 *
 * A provider that carries a school identifier on user and group events reduces
 * all of this to forSchool().
 */
final class TenantResolver
{
    private const int UNRESOLVABLE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly IdpProviders $providers,
    ) {}

    public function resolve(string $provider, string $entityType, string $entityId): ?Tenant
    {
        return match ($entityType) {
            IdpEvent::ENTITY_SCHOOL => $this->forSchool($entityId),
            IdpEvent::ENTITY_USER => $this->forEntity($provider, IdpDirectoryEntry::TYPE_USER, $entityId),
            IdpEvent::ENTITY_GROUP => $this->forEntity($provider, IdpDirectoryEntry::TYPE_GROUP, $entityId),
            default => null,
        };
    }

    public function forSchool(string $schoolId): ?Tenant
    {
        return Tenant::where('idp_school_id', $schoolId)->first();
    }

    /**
     * Index an id against a tenant. Idempotent: a repeated sighting refreshes
     * the row, and a moved entity re-points to its new tenant.
     */
    public function remember(string $entityType, string $idpId, string $tenantId, ?string $provider = null): void
    {
        if ($idpId === '') {
            return;
        }

        $provider ??= (string) Tenant::find($tenantId)?->sso_provider;

        IdpDirectoryEntry::updateOrCreate(
            ['provider' => $provider, 'entity_type' => $entityType, 'idp_id' => $idpId],
            ['tenant_id' => $tenantId],
        );
    }

    /**
     * @param  list<string>  $idpIds
     */
    public function rememberMany(string $entityType, array $idpIds, string $tenantId, ?string $provider = null): void
    {
        foreach (array_unique($idpIds) as $id) {
            $this->remember($entityType, $id, $tenantId, $provider);
        }
    }

    public function forget(string $provider, string $entityType, string $idpId): void
    {
        IdpDirectoryEntry::where('provider', $provider)
            ->where('entity_type', $entityType)
            ->where('idp_id', $idpId)
            ->delete();
    }

    private function forEntity(string $provider, string $entityType, string $entityId): ?Tenant
    {
        $tenant = $this->fromDirectory($provider, $entityType, $entityId);

        if ($tenant !== null) {
            return $tenant;
        }

        if ($this->isKnownUnresolvable($provider, $entityType, $entityId)) {
            return null;
        }

        $tenant = $this->discover($provider, $entityType, $entityId);

        if ($tenant === null) {
            $this->rememberUnresolvable($provider, $entityType, $entityId);
        }

        return $tenant;
    }

    private function fromDirectory(string $provider, string $entityType, string $entityId): ?Tenant
    {
        $entry = IdpDirectoryEntry::where('provider', $provider)
            ->where('entity_type', $entityType)
            ->where('idp_id', $entityId)
            ->first();

        if ($entry === null) {
            return null;
        }

        $tenant = Tenant::find($entry->tenant_id);

        if ($tenant === null) {
            // The tenant was deleted under the index, so drop the entry and let
            // a rescan find the entity elsewhere or conclude it is gone.
            $entry->delete();

            return null;
        }

        return $tenant;
    }

    /**
     * Walk the tenants on this provider until the entity turns up, indexing
     * every id seen on the way.
     */
    private function discover(string $provider, string $entityType, string $entityId): ?Tenant
    {
        $tenants = Tenant::whereNotNull('idp_school_id')->where('sso_provider', $provider)->get();

        if ($tenants->isEmpty()) {
            return null;
        }

        Log::info('the provider: scanning schools for an unindexed entity', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'tenants' => $tenants->count(),
        ]);

        $match = null;

        foreach ($tenants as $tenant) {
            try {
                $found = $entityType === IdpDirectoryEntry::TYPE_GROUP
                    ? $this->indexGroups($tenant)
                    : $this->indexPeople($tenant);
            } catch (DirectoryException $e) {
                // One unreachable school must not abort the scan, though it does
                // make a null result "not found yet" rather than "absent".
                Log::warning('the provider: school scan failed, continuing', [
                    'tenant' => $tenant->instance_code,
                    'reason' => $e->reason,
                ]);

                continue;
            }

            if ($match === null && in_array($entityId, $found, true)) {
                $match = $tenant;
            }
        }

        return $match;
    }

    /**
     * @return list<string> every person id seen at this school
     */
    private function indexPeople(Tenant $tenant): array
    {
        $schoolId = (string) $tenant->idp_school_id;
        $provider = $this->providers->forTenant($tenant);

        if ($provider === null) {
            return [];
        }

        $directory = $this->providers->directory($provider);

        // users() merges both provider listings, which overlap without nesting:
        // a person may never sign in, a user may have no person record.
        $people = $directory->users($schoolId);

        $personIds = [];
        $groupIds = [];

        foreach ($people as $person) {
            $personIds[] = $person->id;
            $groupIds = array_merge($groupIds, $person->groupIds());
        }

        $this->rememberMany(IdpDirectoryEntry::TYPE_USER, $personIds, $tenant->id);
        // The nested group refs arrive in the same response, at no extra call.
        $this->rememberMany(IdpDirectoryEntry::TYPE_GROUP, $groupIds, $tenant->id);

        return array_values(array_unique($personIds));
    }

    /**
     * @return list<string> every group id seen at this school
     */
    private function indexGroups(Tenant $tenant): array
    {
        $provider = $this->providers->forTenant($tenant);

        if ($provider === null) {
            return [];
        }

        $groupIds = array_map(
            fn ($group): string => $group->id,
            $this->providers->directory($provider)->groups((string) $tenant->idp_school_id),
        );

        $this->rememberMany(IdpDirectoryEntry::TYPE_GROUP, $groupIds, $tenant->id);

        return array_values(array_unique($groupIds));
    }

    private function isKnownUnresolvable(string $provider, string $entityType, string $entityId): bool
    {
        return (bool) Cache::get($this->unresolvableKey($provider, $entityType, $entityId), false);
    }

    private function rememberUnresolvable(string $provider, string $entityType, string $entityId): void
    {
        Log::info('the provider: entity belongs to no tenant we host', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);

        Cache::put(
            $this->unresolvableKey($provider, $entityType, $entityId),
            true,
            self::UNRESOLVABLE_TTL_SECONDS,
        );
    }

    private function unresolvableKey(string $provider, string $entityType, string $entityId): string
    {
        return "idp_unresolvable:{$entityType}:{$entityId}";
    }
}
