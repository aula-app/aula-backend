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
    }
}
