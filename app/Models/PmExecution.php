<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmExecution extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'pm_schedule_date_id',
        'machine_id',
        'operator_id',
        'operator_name_snapshot',
        'status',
        'started_at',
        'submitted_at',
        'approved_at',
        'approved_by',
        'approved_by_name_snapshot',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function scheduleDate(): BelongsTo
    {
        return $this->belongsTo(PmScheduleDate::class, 'pm_schedule_date_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PmExecutionItem::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(PmExecutionMedia::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(PmExecutionHistory::class);
    }

    public function scopeByStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): void
    {
        $query->whereBetween('submitted_at', [$startDate, $endDate]);
    }
}
