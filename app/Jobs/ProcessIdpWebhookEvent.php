<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\IdpWebhookEvent;
use App\Models\Tenant;
use App\Services\Idp\Dto\IdpEvent;
use App\Services\Idp\IdpProviders;
use App\Services\Idp\Sync\GroupSync;
use App\Services\Idp\Sync\SchoolSync;
use App\Services\Idp\Sync\SyncOutcome;
use App\Services\Idp\Sync\UserSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    protected int $tries = 5;

    /**
     * Spread over roughly twenty minutes: long enough to ride out a provider
     * outage, short enough to keep a school's data from going stale.
     *
     * @var list<int>
     */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public readonly int $eventId,
    ) {
    }

    public function handle(
        IdpProviders $providers,
        UserSync $userSync,
        GroupSync $groupSync,
        SchoolSync $schoolSync,
    ): void {
        /** @var IdpWebhookEvent **/
        if (($record = $this->findUnprocessedEvent($this->eventId)) === null) {
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

        /** @var Tenant **/
        if (($tenant = $this->resolveTenant($event, $record)) === null) {
            return;
        }
        tenancy()->initialize($tenant);

        $provider = $record->provider;
        Log::info('IdP webhook: processing', [
            'event_id' => $record->id,
            'provider' => $provider,
            'entity_type' => $event->entityType,
            'action' => $event->action,
            'tenant' => $tenant->instance_code,
        ]);

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
        } else {
            $record->tenant_id = $tenant->id;
            $record->markSkipped((string) $outcome->reason);
        }
    }

    public function failed(Throwable $e): void
    {
        $record = IdpWebhookEvent::find($this->eventId);

        $record?->markFailed(substr($e->getMessage(), 0, 1000));

        Log::warning('IdP webhook: giving up on an event', [
            'event_id' => $this->eventId,
            'error' => $e->getMessage(),
        ]);
    }

    private function findUnprocessedEvent(int $eventId): ?IdpWebhookEvent
    {
        $record = IdpWebhookEvent::find($eventId);
        if ($record === null) {
            Log::warning(
                "Cannot process webhook event, the record with eventId: '{$eventId}' not found.",
                ['event_id' => $eventId]
            );
            return null;
        }

        $provider = $record->provider;

        if ($record->status === IdpWebhookEvent::STATUS_PROCESSED) {
            Log::info(
                "Webhook event already processed.",
                ['event_id' => $eventId, 'provider' => $provider, 'tenant_id' => $record->tenant_id]
            );
            // Already applied: a duplicate dispatch, not a duplicate delivery.
            return null;
        }

        return $record;
    }

    private function resolveTenant(IdpEvent $event, IdpWebhookEvent $record): ?Tenant
    {
        $provider = $record->provider;
        if (($schoolId = $event->getSchoolId()) === null) {
            Log::error(
                "Cannot resolve tenant from '{provider}', received no schoolId info.",
                ['event_id' => $this->eventId, 'school_id' => null, 'provider' => $provider]
            );
            $record->markSkipped('tenant_unresolved');
            return null;
        }

        try {
            $tenant = Tenant::where('sso_provider', $provider)
                ->where('idp_school_id', $schoolId)
                ->sole();
        } catch (ModelNotFoundException $e) {
            Log::warning(
                "Cannot resolve tenant from '{provider}' based on received schoolId: '{school_id}'.",
                ['event_id' => $this->eventId, 'school_id' => $schoolId, 'provider' => $provider]
            );
            $record->markSkipped('tenant_unresolved');
            return null;
        }

        return $tenant;
    }
}
