<?php

declare(strict_types=1);

namespace App\Services\Idp\Sync;

/**
 * What a sync did with an event, so the job can record it without the sync
 * services needing to know about the event log or about queue semantics.
 *
 * "Skipped" is a success: there was legitimately nothing to do. Failures are
 * exceptions, because those are the ones the queue should retry.
 */
final readonly class SyncOutcome
{
    private function __construct(
        public bool $wasProcessed,
        public ?string $reason = null,
    ) {}

    public static function processed(): self
    {
        return new self(true);
    }

    public static function skipped(string $reason): self
    {
        return new self(false, $reason);
    }
}
