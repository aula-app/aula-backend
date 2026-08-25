<?php

declare(strict_types=1);

namespace App\Services\Idp;

use RuntimeException;
use Throwable;

final class DirectoryException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct("Identity directory request failed: {$reason}", $status ?? 0, $previous);
    }
}
