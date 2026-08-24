<?php

declare(strict_types=1);

namespace App\Http\Controllers\Idp;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIdpWebhookEvent;
use App\Models\IdpWebhookEvent;
use App\Services\Idp\IdpProviders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives identity-provider webhooks.
 *
 * VerifyIdpWebhookSignature checks the signature and the provider's
 * WebhookAdapter normalises the payload, so what is left here is durable
 * capture: the delivery is written to `idp_webhook_events`, acknowledged with
 * 202, and ProcessIdpWebhookEvent does the work.
 *
 * The status codes drive the provider's own retries:
 *
 *   401  bad or missing signature
 *   404  provider not configured here
 *   422  uninterpretable envelope, which no retry can fix
 *   202  accepted, and every retry after this belongs to the aula queue
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly IdpProviders $providers,
    ) {}

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
