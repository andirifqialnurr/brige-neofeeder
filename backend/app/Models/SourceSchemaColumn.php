<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceSchemaColumn extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'is_nullable' => 'boolean',
        'is_primary_key' => 'boolean',
        'is_unique_key' => 'boolean',
        'is_candidate_primary_key' => 'boolean',
        'is_foreign_key' => 'boolean',
        'is_candidate_relation' => 'boolean',
        'sample_values' => 'array',
    ];

    public function sourceSchemaTable(): BelongsTo
    {
        return $this->belongsTo(SourceSchemaTable::class);
    }
}
