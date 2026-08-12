<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Idp\SchoolImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls a school's directory into its tenant, off the request.
 *
 * The first SSO login triggers this. It used to run inline in the callback,
 * which meant the browser held the redirect open for the whole import and the
 * frontend could never observe it in progress — by the time anyone could ask,
 * it was already done. Queued, the login returns at once and the frontend polls
 * `idp_import_status` to hold the user on a setup screen.
 *
 * The tenant is marked `running` synchronously before this is dispatched, so
 * there is no window where a school looks ready because its import has not
 * been picked up yet.
 */
class ImportSchoolForTenant implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(
        public readonly string $tenantId,
    ) {}

    public function handle(SchoolImport $import): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(fn () => $import->run($tenant));

        $this->advanceMigration($tenant);
    }

    /**
     * Move a migrating school on once its directory has landed.
     *
     * Only the migration path has a state to advance: a greenfield school has
     * no `idp_migration_status` at all and is finished when the import is. Left
     * undone, a migrating school sits on `importing` for good — the import is
     * complete, every screen still says it is running, and nothing will ever
     * say otherwise.
     */
    private function advanceMigration(Tenant $tenant): void
    {
        $tenant->refresh();

        if ($tenant->idp_migration_status !== Tenant::IDP_MIGRATION_IMPORTING) {
            return;
        }

        if ($tenant->idp_import_status === SchoolImport::STATUS_FAILED) {
            // Back to the review: that is where an admin can look at the
            // proposal again and retry.
            $tenant->update(['idp_migration_status' => Tenant::IDP_MIGRATION_REVIEWING]);

            return;
        }

        if ($tenant->idp_import_status !== SchoolImport::STATUS_COMPLETED) {
            return;
        }

        // The directory is in. What remains is people linking their own
        // accounts, which happens as they sign in over the following days.
        $tenant->update(['idp_migration_status' => Tenant::IDP_MIGRATION_LINKING]);

        Log::info('IdP: import finished, school is now linking accounts', [
            'tenant' => $tenant->instance_code,
            'rooms' => $tenant->idp_import_rooms,
            'users' => $tenant->idp_import_users,
        ]);
    }

    /**
     * SchoolImport records its own failure, but only for exceptions it sees.
     * A job that dies outside it — killed worker, timeout — would otherwise
     * leave the tenant stuck on `running` and the frontend waiting forever.
     */
    public function failed(Throwable $e): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        Log::error('IdP: school import job gave up', [
            'tenant' => $tenant->instance_code,
            'error' => $e->getMessage(),
        ]);

        if ($tenant->idp_import_status !== SchoolImport::STATUS_FAILED) {
            $tenant->update([
                'idp_import_status' => SchoolImport::STATUS_FAILED,
                'idp_import_error' => substr($e->getMessage(), 0, 1000),
                'idp_import_finished_at' => now(),
            ]);
        }

        // A migration that gave up belongs back at the review, not stranded on
        // a progress screen that will never finish.
        if ($tenant->idp_migration_status === Tenant::IDP_MIGRATION_IMPORTING) {
            $tenant->update(['idp_migration_status' => Tenant::IDP_MIGRATION_REVIEWING]);
        }
    }
}
