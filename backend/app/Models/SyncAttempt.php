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
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'identity_payload' => 'array',
        'attempted_at' => 'datetime',
    ];

    public function stagingRecord(): BelongsTo
    {
        return $this->belongsTo(StagingRecord::class);
    }
}
