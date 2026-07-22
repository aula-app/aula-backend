<?php

declare(strict_types=1);

namespace App\Services\Idp;

use App\Models\IdpDirectoryEntry;
use App\Models\Tenant;
use App\Services\Idp\Dto\IdpEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Answers "which tenant does this directory entity belong to?".
 *
 * IdpSchool events are easy: `tenants.idp_school_id` is a direct mapping.
 * IdpUser and group events are not — those payloads carry no school identifier
 * at all, so there may be nothing in
 * the event itself to route on.
 *
 * Two-step answer:
 *
 *   1. Consult the directory index, which is populated every time a school's
 *      people or groups are read.
 *   2. On a miss, scan that provider's tenants through its directory. Every
 *      id seen along the way is indexed, so one scan warms a whole school and
 *      the next event for any of its members is a single lookup.
 *
 * Ids that resolve to nothing are remembered for a while so a school we do not
 * host cannot make every one of its events trigger a fresh scan.
 *
 * If a provider carries a school identifier on its user and group events, all
 * of this collapses to the school lookup and can be deleted.
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
     * Index an id against a tenant. Idempotent: a repeated sighting just
     * refreshes the row, and a moved entity re-points to its new tenant.
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
            // Tenant was deleted out from under the index. Drop the entry so a
            // rescan can find the entity elsewhere, or conclude it is gone.
            $entry->delete();

            return null;
        }

        return $tenant;
    }

    /**
     * Walk the the provider-enabled tenants until the entity turns up, indexing
     * everything seen on the way.
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
                // One unreachable school must not abort the whole scan, but it
                // does mean a null result here is "not found yet", not "absent".
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

        // People and users are overlapping sets, not nested ones: a person may
        // never sign in, and a user may exist without being a configured person.
        $people = $directory->users($schoolId);

        $personIds = [];
        $groupIds = [];

        foreach ($people as $person) {
            $personIds[] = $person->id;
            $groupIds = array_merge($groupIds, $person->groupIds());
        }

        $this->rememberMany(IdpDirectoryEntry::TYPE_USER, $personIds, $tenant->id);
        // The nested group references come for free with this response.
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
