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

    protected $casts = ['headers' => 'array', 'snapshot' => 'array', 'row_count' => 'integer', 'connection_config' => 'encrypted:array'];

    public function schemaTables(): HasMany
    {
        return $this->hasMany(SourceSchemaTable::class);
    }
}
