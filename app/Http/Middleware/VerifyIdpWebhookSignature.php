<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Idp\IdpProviders;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies an inbound webhook against the provider named in the route.
 *
 * The scheme itself belongs to the provider's WebhookAdapter — signatures are
 * where identity providers differ most — so this only resolves the provider,
 * supplies the secret, and fails closed.
 */
class VerifyIdpWebhookSignature
{
    public function __construct(
        private readonly IdpProviders $providers,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $provider = (string) $request->route('provider');

        if (! $this->providers->isConfigured($provider)) {
            return $this->reject('unknown_provider', 404);
        }

        $secret = (string) $this->providers->config($provider, 'webhook_secret');

        if ($secret === '') {
            // Fail closed. An unset secret would otherwise turn this endpoint
            // into an unauthenticated write path into tenant databases.
            Log::error('IdP webhook: no webhook secret configured, rejecting delivery', [
                'provider' => $provider,
            ]);

            return $this->reject('webhook_not_configured', 500);
        }

        if (! $this->providers->webhook($provider)->verify($request, $secret)) {
            Log::warning('IdP webhook: signature verification failed', [
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);

            return $this->reject('signature_invalid');
        }

        return $next($request);
    }

    private function reject(string $error, int $status = 401): JsonResponse
    {
        return response()->json(['error' => $error], $status);
    }
}
