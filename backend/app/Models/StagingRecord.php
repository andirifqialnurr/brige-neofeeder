<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StagingRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'import_batch_id',
        'channel',
        'sheet_name',
        'row_number',
        'operation',
        'natural_key',
        'raw_row',
        'normalized_row',
        'validation_result',
        'status',
    ];

    protected $casts = [
        'raw_row' => 'array',
        'normalized_row' => 'array',
        'validation_result' => 'array',
    ];

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function syncAttempts(): HasMany
    {
        return $this->hasMany(SyncAttempt::class);
    }
}
