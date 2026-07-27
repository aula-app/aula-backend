<?php

declare(strict_types=1);

namespace App\Services\Idp\Sync;

use App\Models\Tenant;
use App\Services\Idp\Dto\IdpEvent;
use App\Services\Idp\IdpProviders;
use Illuminate\Support\Facades\Log;

/**
 * Applies a `school` webhook to the tenant record.
 *
 * The only school attribute aula has a home for is the name. Address, location,
 * official id and schooling level are read and logged but not stored — there
 * are no columns for them and nothing currently reads them, so adding some
 * would be schema written on speculation. If a consumer appears, the fetch is
 * already here and only persistence needs adding.
 *
 * Deletes are deliberately not honoured. Dropping a tenant destroys a school's
 * entire aula database, which is not a decision to hand to an inbound webhook.
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

        // tenants.name is unique. Two schools that rename into each other would
        // otherwise fail the write and put the event into the retry loop for
        // nothing, so the collision is reported and the rename dropped.
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
