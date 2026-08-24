<?php

declare(strict_types=1);

namespace App\Services\Idp\Contracts;

use App\Services\Idp\Dto\IdpEvent;
use Illuminate\Http\Request;

/**
 * Turns one provider's webhook delivery into an IdpEvent.
 *
 * Signature schemes and payload envelopes are where providers differ most, so
 * both live behind this instead of in WebhookController.
 */
interface WebhookAdapter
{
    /**
     * Verify the delivery is authentic. An implementation must compare in
     * constant time and read the raw body, since a decode and re-encode round
     * trip does not reproduce the signed bytes.
     */
    public function verify(Request $request, string $secret): bool;

    /**
     * Normalise the delivery, or null when the envelope cannot be interpreted.
     */
    public function parse(Request $request): ?IdpEvent;
}
