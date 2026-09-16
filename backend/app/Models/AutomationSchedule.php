<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationSchedule extends Model
{
    use HasUuids;

    public const FREQUENCIES = ['hourly', 'daily', 'weekly'];

    public const MODE_FULL = 'full';

    public const MODES = [self::MODE_FULL];

    public const ALERT_WINDOW_DAYS = 7;

    public const ALERT_MIN_RUNS = 3;

    public const RUN_STATUSES = ['running', 'success', 'failed'];

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'next_run_at' => 'datetime',
        'last_started_at' => 'datetime',
        'last_completed_at' => 'datetime',
        'error_rate_threshold' => 'integer',
        'alert_active' => 'boolean',
        'alert_triggered_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(SourceConnection::class, 'source_connection_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(MappingProfile::class, 'mapping_profile_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'last_batch_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationScheduleRun::class);
    }
}
