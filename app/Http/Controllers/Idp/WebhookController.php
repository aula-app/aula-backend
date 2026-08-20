<?php

declare(strict_types=1);

namespace App\Http\Controllers\Idp;

use App\Jobs\ProcessIdpWebhookEvent;
use App\Models\IdpWebhookEvent;
use App\Services\Idp\IdpProviders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Receives identity-provider webhooks.
 *
 * The signature is checked by middleware and the payload is normalised by the
 * provider's adapter, so what is left here is durable capture: the delivery is
 * written to the central event log, acknowledged with 202, and the work happens
 * on the queue.
 *
 * Status codes matter more than usual because they drive the provider's own
 * retries:
 *
 *   401  bad or missing signature     — worth retrying only if transient
 *   404  provider not configured here
 *   422  envelope we cannot interpret — a contract violation, retries will not help
 *   202  accepted                     — anything after this is our problem, and
 *                                       our own queue owns those retries
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly IdpProviders $providers,
    ) {
    }

    public function handle(Request $request, string $provider): JsonResponse
    {
        $event = $this->providers->webhook($provider)->parse($request);

        if ($event === null) {
            Log::warning('IdP webhook: rejected an uninterpretable delivery', ['provider' => $provider]);

            return response()->json(['error' => 'payload_invalid'], 422);
        }

        $record = IdpWebhookEvent::create([
            'provider' => $provider,
            'entity_type' => $event->entityType,
            'action' => $event->action,
            'entity_id' => $event->entityId,
            'updated_properties' => $event->updatedProperties,
            'payload' => $event->payload,
            'status' => IdpWebhookEvent::STATUS_PENDING,
            'received_at' => now(),
        ]);

        ProcessIdpWebhookEvent::dispatch($record->id);

        Log::info('IdP webhook: accepted', [
            'event_id' => $record->id,
            'provider' => $provider,
            'entity_type' => $event->entityType,
            'action' => $event->action,
            'entity_id' => $event->entityId,
        ]);

        return response()->json(['status' => 'accepted', 'id' => $record->id], 202);
    }
}
