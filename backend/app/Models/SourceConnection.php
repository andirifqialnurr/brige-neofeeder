<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceConnection extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['snapshot', 'connection_config'];

    protected $casts = [
        'headers' => 'array',
        'snapshot' => 'array',
        'row_count' => 'integer',
        'connection_config' => 'encrypted:array',
        'schema_discovery_started_at' => 'datetime',
        'schema_discovered_at' => 'datetime',
        'snapshot_started_at' => 'datetime',
        'snapshot_refreshed_at' => 'datetime',
    ];

    public function schemaTables(): HasMany
    {
        return $this->hasMany(SourceSchemaTable::class);
    }
}
