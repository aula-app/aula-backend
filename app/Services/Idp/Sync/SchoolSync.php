<?php

declare(strict_types=1);

namespace App\Services\Idp\Sync;

use App\Models\Tenant;
use App\Services\Idp\Dto\IdpEvent;
use App\Services\Idp\IdpProviders;
use Illuminate\Support\Facades\Log;

/**
 * Applies a `school` event to the tenants row.
 *
 * `tenants.name` is the only school attribute aula has a column for. Address,
 * location, official id and schooling level are read and logged instead, since
 * nothing reads them yet; persisting them later needs no new fetch.
 *
 * ACTION_DELETE is not honoured: dropping a tenant destroys a school's whole
 * aula database, which is not a decision for an inbound webhook.
 */
final class SchoolSync
{
    public function __construct(
        private readonly IdpProviders $providers,
    ) {}

    public function handle(IdpEvent $event, Tenant $tenant, string $provider): SyncOutcome
    {
        if ($event->action === IdpEvent::ACTION_DELETE) {
            Log::warning('IdP: school deleted upstream — the aula tenant needs an operator decision', [
                'tenant' => $tenant->instance_code,
                'idp_school_id' => $event->entityId,
            ]);

            return SyncOutcome::skipped('school_delete_needs_operator');
        }

        $school = $this->providers->directory($provider)->school($event->entityId);

        if ($school === null) {
            return SyncOutcome::skipped('school_not_found_upstream');
        }

        $this->renameTenant($tenant, $school->name);

        Log::info('IdP: school attributes with no home in aula', [
            'tenant' => $tenant->instance_code,
            'address' => $school->address,
            'location' => $school->location,
            'official_id' => $school->officialId,
            'schooling_level' => $school->schoolingLevel,
        ]);

        return SyncOutcome::processed();
    }

    private function renameTenant(Tenant $tenant, string $name): void
    {
        if ($name === '' || $name === $tenant->name) {
            return;
        }

        // tenants.name is unique. Two schools renaming into each other would
        // fail the write and put the event back in the retry loop, so the
        // collision is logged and the rename dropped.
        $taken = Tenant::where('name', $name)->where('id', '!=', $tenant->id)->exists();

        if ($taken) {
            Log::warning('IdP: school rename collides with another tenant', [
                'tenant' => $tenant->instance_code,
                'wanted' => $name,
            ]);

            return;
        }

        $previous = $tenant->name;
        $tenant->update(['name' => $name]);

        Log::info('IdP: renamed a tenant from an IDM webhook', [
            'tenant' => $tenant->instance_code,
            'from' => $previous,
            'to' => $name,
        ]);
    }
}
