<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SourceConnection extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['snapshot', 'connection_config'];

    protected $casts = ['headers' => 'array', 'snapshot' => 'array', 'row_count' => 'integer', 'connection_config' => 'encrypted:array'];
}
