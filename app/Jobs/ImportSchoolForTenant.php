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
 * Dispatched by SsoController::bootstrapIdpTenant() on the first SSO login and
 * by MergeProposalController::apply(). The login returns at once and the
 * frontend polls ImportStatusController to hold the user on a setup screen.
 *
 * `idp_import_status` is written to STATUS_PENDING before the dispatch, so
 * there is no window in which a school looks ready because the job has not been
 * picked up yet.
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
     * Move a migrating tenant on once its directory has landed.
     *
     * Only a migration has a state to advance: a tenant with no prior aula use
     * carries no `idp_migration_status` and is finished when the import is.
     * Without this, a migrating tenant stays on IDP_MIGRATION_IMPORTING after
     * the import has completed.
     */
    private function advanceMigration(Tenant $tenant): void
    {
        $tenant->refresh();

        if ($tenant->idp_migration_status !== Tenant::IDP_MIGRATION_IMPORTING) {
            return;
        }

        if ($tenant->idp_import_status === SchoolImport::STATUS_FAILED) {
            // Back to IDP_MIGRATION_REVIEWING, where an admin can revisit the
            // proposal and retry.
            $tenant->update(['idp_migration_status' => Tenant::IDP_MIGRATION_REVIEWING]);

            return;
        }

        if ($tenant->idp_import_status !== SchoolImport::STATUS_COMPLETED) {
            return;
        }

        // The directory is in. What remains is accounts linking themselves as
        // their owners sign in.
        $tenant->update(['idp_migration_status' => Tenant::IDP_MIGRATION_LINKING]);

        Log::info('IdP: import finished, school is now linking accounts', [
            'tenant' => $tenant->instance_code,
            'rooms' => $tenant->idp_import_rooms,
            'users' => $tenant->idp_import_users,
        ]);
    }

    /**
     * SchoolImport records its own failure, but only for exceptions it catches.
     * A job killed outside it, by a dead worker or a timeout, would leave the
     * tenant on STATUS_RUNNING and ImportStatusController reporting not ready.
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

        // A migration that gave up goes back to IDP_MIGRATION_REVIEWING instead
        // of staying on a progress screen that never completes.
        if ($tenant->idp_migration_status === Tenant::IDP_MIGRATION_IMPORTING) {
            $tenant->update(['idp_migration_status' => Tenant::IDP_MIGRATION_REVIEWING]);
        }
    }
}
