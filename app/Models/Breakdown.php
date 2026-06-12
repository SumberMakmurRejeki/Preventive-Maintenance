<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Breakdown extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'breakdown_code',
        'machine_id',
        'pm_checksheet_part_id',
        'machine_name_snapshot',
        'location_name_snapshot',
        'part_name_snapshot',
        'custom_part_name',
        'problem',
        'open_note',
        'status',
        'breakdown_at',
        'created_by',
        'created_by_name_snapshot',
        'root_cause',
        'action_taken',
        'countermeasure',
        'closed_at',
        'closed_by',
        'closed_by_name_snapshot',
        'downtime_minutes',
    ];

    protected function casts(): array
    {
        return [
            'breakdown_at' => 'datetime',
            'closed_at' => 'datetime',
            'downtime_minutes' => 'integer',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(PmChecksheetPart::class, 'pm_checksheet_part_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function media(): HasMany
    {
        return $this->hasMany(BreakdownMedia::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(BreakdownHistory::class);
    }

    public function scopeByStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): void
    {
        $query->whereBetween('breakdown_at', [$startDate, $endDate]);
    }
}
