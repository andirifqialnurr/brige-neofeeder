<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NeoFeederConnection extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'base_url',
        'username',
        'encrypted_password',
        'status',
        'last_token_refreshed_at',
        'last_checked_at',
        'metadata',
    ];

    protected $hidden = [
        'encrypted_password',
    ];

    protected $casts = [
        'last_token_refreshed_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
