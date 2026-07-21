<?php

declare(strict_types=1);

namespace App\Services\Idp\Contracts;

use App\Services\Idp\Dto\IdpEvent;
use Illuminate\Http\Request;

/**
 * Turns one provider's webhook into aula's own event shape.
 *
 * Signature schemes and payload envelopes are where identity providers differ
 * most, so both live behind this rather than in the endpoint.
 */
interface WebhookAdapter
{
    /**
     * Verify the delivery is authentic. Implementations must compare in
     * constant time and read the raw body, since a decode/re-encode round trip
     * would not reproduce the signed bytes.
     */
    public function verify(Request $request, string $secret): bool;

    /**
     * Normalise the delivery, or null when the envelope cannot be interpreted.
     */
    public function parse(Request $request): ?IdpEvent;
}
