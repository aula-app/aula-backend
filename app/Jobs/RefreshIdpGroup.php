<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Idp\Sync\GroupSync;
use App\Services\Idp\Sync\SyncOutcome;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Converges one directory-sourced room on its group upstream: name, status
 * and members, importing members aula does not hold. A group gone upstream
 * archives the room, unless the school itself has stopped answering.
 *
 * Dispatched by ProcessIdpWebhookEvent for every group of a person event.
 */
class RefreshIdpGroup implements ShouldBeUniqueUntilProcessing, ShouldQueue
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
        public readonly string $groupId,
    ) {}

    /**
     * One pending refresh per group: a dispatch while one is queued is
     * dropped, and the lock is released when processing starts, so a change
     * arriving mid-run still queues a new one.
     */
    public function uniqueId(): string
    {
        return $this->tenantId.':'.$this->groupId;
    }

    public function handle(GroupSync $groupSync): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $outcome = $tenant->run(fn (): SyncOutcome => $groupSync->handle($tenant, $this->provider, $this->groupId));

        if (! $outcome->wasProcessed) {
            Log::info('IdP: group refresh changed nothing', [
                'tenant' => $tenant->instance_code,
                'idp_group_id' => $this->groupId,
                'reason' => $outcome->reason,
            ]);
        }
    }
}
