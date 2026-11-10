<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Idp\Sync\UserSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs UserSync::handle() for one account on the queue.
 *
 * Dispatched by SsoController on every login of an imported directory tenant.
 */
class UserSyncJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /** Lock lifetime if the job is lost before it is processed. */
    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $provider,
        public readonly string $userId,
    ) {}

    /**
     * One pending sync per account: a login while one is queued is dropped.
     */
    public function uniqueId(): string
    {
        return $this->tenantId.':'.$this->userId;
    }

    public function handle(UserSync $userSync): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(fn () => $userSync->handle($tenant, $this->provider, $this->userId));
    }
}
