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
use Illuminate\Support\Facades\Log;

/**
 * Converges one directory-sourced room on its group upstream: name, status,
 * members and per-room roles, importing members aula does not hold.
 *
 * Dispatched by ProcessIdpWebhookEvent for every group of a person event and
 * by ReconcileIdpSchool for listed groups without an active room.
 */
class RefreshIdpGroup implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public readonly string $tenantId,
        public readonly string $provider,
        public readonly string $groupId,
    ) {}

    public function handle(GroupSync $groups): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $found = $tenant->run(fn (): bool => $groups->refreshGroup($this->groupId, $tenant, $this->provider));

        if (! $found) {
            Log::info('IdP: group to refresh is gone upstream', [
                'tenant' => $tenant->instance_code,
                'idp_group_id' => $this->groupId,
            ]);
        }
    }
}
