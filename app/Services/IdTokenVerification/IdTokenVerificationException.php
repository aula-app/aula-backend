<?php

declare(strict_types=1);

namespace App\Services\IdTokenVerification;

use RuntimeException;
use Throwable;

final class IdTokenVerificationException extends RuntimeException
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct("id_token verification failed: {$reason}", 0, $previous);
    }
}
