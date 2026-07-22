<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One received identity-provider webhook.
 *
 * Rows are written before any processing so a delivery is never lost to a
 * transient failure downstream, and so a replay can be reconstructed from
 * what the provider actually sent rather than from what we inferred.
 *
 * Lives on the central connection: the job that processes an event switches
 * to a tenant database part-way through, and the audit trail must not follow
 * it there.
 */
class IdpWebhookEvent extends Model
{
    use CentralConnection;

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PROCESSED = 'processed';

    /** Nothing to do: the entity is not one we track. */
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
