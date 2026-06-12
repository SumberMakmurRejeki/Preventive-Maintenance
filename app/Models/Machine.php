<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'location_id',
        'machine_code',
        'machine_name',
        'qr_token',
        'qr_code_path',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'machine_code';
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function checksheetAssignments(): HasMany
    {
        return $this->hasMany(PmChecksheetMachine::class);
    }

    public function scheduleDates(): HasMany
    {
        return $this->hasMany(PmScheduleDate::class);
    }

    public function pmExecutions(): HasMany
    {
        return $this->hasMany(PmExecution::class);
    }

    public function breakdowns(): HasMany
    {
        return $this->hasMany(Breakdown::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): void
    {
        $query->where('is_active', false);
    }
}
