<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'source_type',
        'file_path',
        'template_version',
        'status',
        'summary',
    ];

    protected $casts = [
        'summary' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function stagingRecords(): HasMany
    {
        return $this->hasMany(StagingRecord::class);
    }
}
