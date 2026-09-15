<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MappingProfile extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['version' => 'integer'];

    public function versions(): HasMany
    {
        return $this->hasMany(MappingProfileVersion::class);
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(MappingProfileVersion::class)->ofMany('version', 'max');
    }
}
