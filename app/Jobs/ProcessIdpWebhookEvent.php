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
 * WebhookController acknowledges a delivery with 202 and the work happens here,
 * so the retries belong to the aula queue rather than to the provider.
 *
 * Runs in central context and initialises the resolved tenant for the sync.
 * `idp_webhook_events` stays on the central connection, so progress is recorded
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
     * outage, short enough to keep a school's data from going stale.
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
            // Already applied: a duplicate dispatch, not a duplicate delivery.
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
            // A provider notifies about every school it knows, including the
            // schools that do not use aula.
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
