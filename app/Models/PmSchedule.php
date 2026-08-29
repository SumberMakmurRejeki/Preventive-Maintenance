<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmSchedule extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'pm_checksheet_machine_id',
        'frequency_type',
        'weekly_days',
        'monthly_day',
        'operational_from',
        'start_date',
        'generate_until',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'weekly_days' => 'array',
            'monthly_day' => 'integer',
            'operational_from' => 'date',
            'start_date' => 'date',
            'generate_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function checksheetMachine(): BelongsTo
    {
        return $this->belongsTo(PmChecksheetMachine::class, 'pm_checksheet_machine_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scheduleDates(): HasMany
    {
        return $this->hasMany(PmScheduleDate::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): void
    {
        $query->where('is_active', false);
    }

    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): void
    {
        $query->whereDate('start_date', '>=', $startDate)
            ->whereDate('generate_until', '<=', $endDate);
    }
}
