<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Idp\Sync\GroupSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Converges a tenant's rooms on its school's group list.
 *
 * Archives rooms whose group is no longer listed and queues a RefreshIdpGroup
 * for each listed group without an active room. Dispatched by
 * ProcessIdpWebhookEvent after every person event.
 */
class ReconcileIdpSchool implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    /** Lock lifetime if the job is lost before it is processed. */
    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $provider,
    ) {}

    /**
     * One pending reconcile per tenant: a dispatch while one is queued is
     * dropped, and the lock is released when processing starts, so a change
     * arriving mid-run still queues a new one.
     */
    public function uniqueId(): string
    {
        return $this->tenantId;
    }

    public function handle(GroupSync $groups): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(
            fn () => $groups->reconcileSchool($tenant, $this->provider),
        );
    }
}
