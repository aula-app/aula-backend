<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One received identity-provider webhook.
 *
 * WebhookController writes the row before any processing, so a delivery
 * survives a failure in ProcessIdpWebhookEvent and a replay can be rebuilt from
 * the payload as the provider sent it.
 *
 * Central connection: ProcessIdpWebhookEvent initialises a tenant part-way
 * through, and this table must not follow it there.
 */
class IdpWebhookEvent extends Model
{
    use CentralConnection;

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PROCESSED = 'processed';

    /** Nothing to do: no tenant holds the entity, or the sync had no work. */
    public const string STATUS_SKIPPED = 'skipped';

    public const string STATUS_FAILED = 'failed';

    protected $table = 'idp_webhook_events';

    protected $fillable = [
        'provider',
        'entity_type',
        'action',
        'entity_id',
        'updated_properties',
        'payload',
        'tenant_id',
        'status',
        'attempts',
        'error',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'updated_properties' => 'array',
        'payload' => 'array',
        'attempts' => 'integer',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function markProcessed(?string $tenantId = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_PROCESSED,
            'tenant_id' => $tenantId ?? $this->tenant_id,
            'error' => null,
            'processed_at' => now(),
        ])->save();
    }

    public function markSkipped(string $reason): void
    {
        $this->forceFill([
            'status' => self::STATUS_SKIPPED,
            'error' => $reason,
            'processed_at' => now(),
        ])->save();
    }

    public function markFailed(string $reason): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'error' => $reason,
        ])->save();
    }
}
