<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmScheduleDate extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'pm_schedule_id',
        'machine_id',
        'scheduled_date',
        'status',
        'status_changed_at',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'status_changed_at' => 'datetime',
            'generated_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PmSchedule::class, 'pm_schedule_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(PmExecution::class);
    }

    public function scopeByStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): void
    {
        $query->whereBetween('scheduled_date', [$startDate, $endDate]);
    }
}
