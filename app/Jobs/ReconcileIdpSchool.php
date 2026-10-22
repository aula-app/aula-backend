<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Idp\Sync\GroupSync;
use Illuminate\Bus\Queueable;
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
class ReconcileIdpSchool implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    /**
     * @param  list<string>  $alreadyRefreshed  group ids the same event already queued a refresh for
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $provider,
        public readonly array $alreadyRefreshed = [],
    ) {}

    public function handle(GroupSync $groups): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $toRefresh = $tenant->run(
            fn (): array => $groups->reconcileSchool($tenant, $this->provider, $this->alreadyRefreshed),
        );

        foreach ($toRefresh as $groupId) {
            RefreshIdpGroup::dispatch($this->tenantId, $this->provider, $groupId);
        }
    }
}
