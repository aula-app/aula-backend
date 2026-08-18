<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\IdpWebhookEvent;
use App\Services\Idp\Dto\IdpEvent;
use App\Services\Idp\IdpProviders;
use App\Services\Idp\Sync\GroupSync;
use App\Services\Idp\Sync\SchoolSync;
use App\Services\Idp\Sync\SyncOutcome;
use App\Services\Idp\Sync\UserSync;
use App\Services\Idp\TenantResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes one captured identity-provider webhook.
 *
 * The endpoint acknowledges deliveries with 202 and the work happens here, so
 * retries are ours rather than the provider's.
 *
 * Runs in central context and switches into the resolved tenant for the sync.
 * The event log stays on the central connection, so progress is recorded even
 * while a tenant database is active.
 */
class ProcessIdpWebhookEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * Spread over roughly twenty minutes: long enough to ride out a provider
     * outage, short enough that a school's data is not stale for an afternoon.
     *
     * @var list<int>
     */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public readonly int $eventId,
    ) {}

    public function handle(
        IdpProviders $providers,
        TenantResolver $resolver,
        UserSync $userSync,
        GroupSync $groupSync,
        SchoolSync $schoolSync,
    ): void {
        $record = IdpWebhookEvent::find($this->eventId);

        if ($record === null) {
            return;
        }

        if ($record->status === IdpWebhookEvent::STATUS_PROCESSED) {
            // Already applied — a duplicate dispatch, not a duplicate delivery.
            return;
        }

        $record->increment('attempts');

        $event = new IdpEvent(
            entityType: (string) $record->entity_type,
            action: (string) $record->action,
            entityId: (string) $record->entity_id,
            updatedProperties: (array) ($record->updated_properties ?? []),
            payload: (array) $record->payload,
        );

        $provider = (string) $record->provider;
        $tenant = $resolver->resolve($provider, $event->entityType, $event->entityId);

        if ($tenant === null) {
            // Providers notify about every school they know, including ones
            // that do not use aula. Nothing to do, and nothing wrong.
            $record->markSkipped('tenant_unresolved');

            return;
        }

        tenancy()->initialize($tenant);

        try {
            $outcome = match ($event->entityType) {
                IdpEvent::ENTITY_USER => $userSync->handle($event, $tenant, $provider),
                IdpEvent::ENTITY_GROUP => $groupSync->handle($event, $tenant, $provider),
                IdpEvent::ENTITY_SCHOOL => $schoolSync->handle($event, $tenant, $provider),
                default => SyncOutcome::skipped('entity_type_unhandled'),
            };
        } finally {
            tenancy()->end();
        }

        if ($outcome->wasProcessed) {
            $record->markProcessed($tenant->id);

            Log::info('IdP webhook: processed', [
                'event_id' => $record->id,
                'provider' => $provider,
                'entity_type' => $event->entityType,
                'action' => $event->action,
                'tenant' => $tenant->instance_code,
            ]);

            return;
        }

        $record->tenant_id = $tenant->id;
        $record->markSkipped((string) $outcome->reason);
    }

    public function failed(Throwable $e): void
    {
        $record = IdpWebhookEvent::find($this->eventId);

        $record?->markFailed(substr($e->getMessage(), 0, 1000));

        Log::error('IdP webhook: giving up on an event', [
            'event_id' => $this->eventId,
            'error' => $e->getMessage(),
        ]);
    }
}
