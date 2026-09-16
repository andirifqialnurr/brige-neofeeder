<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceSchemaTable extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'estimated_rows' => 'integer',
        'primary_key_columns' => 'array',
        'candidate_key_columns' => 'array',
    ];

    public function sourceConnection(): BelongsTo
    {
        return $this->belongsTo(SourceConnection::class);
    }

    public function columns(): HasMany
    {
        return $this->hasMany(SourceSchemaColumn::class)->orderBy('ordinal_position');
    }
}
