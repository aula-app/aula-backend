<?php

declare(strict_types=1);

namespace App\Services\IdTokenVerification;

use RuntimeException;
use Throwable;

class IdTokenVerificationException extends RuntimeException
{
    public function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct("id_token verification failed: {$reason}", 0, $previous);
    }
}
