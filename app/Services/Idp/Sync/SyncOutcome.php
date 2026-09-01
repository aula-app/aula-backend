<?php

declare(strict_types=1);

namespace App\Services\Idp\Sync;

/**
 * What a sync did with an event, so ProcessIdpWebhookEvent can record it
 * without the sync services knowing about the event log or queue semantics.
 *
 * skipped() is a success: there was nothing to do. A failure is an exception,
 * which is what the queue retries.
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
