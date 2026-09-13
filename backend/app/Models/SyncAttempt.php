<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncAttempt extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'staging_record_id',
        'action',
        'status',
        'request_payload',
        'response_payload',
        'error_code',
        'error_desc',
        'identity_payload',
        'attempted_at',
        'idempotency_key', 'approval_hash', 'retry_of', 'retry_safe',
        'execution_count', 'request_started_at', 'completed_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'identity_payload' => 'array',
        'attempted_at' => 'datetime',
        'request_started_at' => 'datetime', 'completed_at' => 'datetime',
        'retry_safe' => 'boolean', 'execution_count' => 'integer',
    ];

    public function stagingRecord(): BelongsTo
    {
        return $this->belongsTo(StagingRecord::class);
    }
}
