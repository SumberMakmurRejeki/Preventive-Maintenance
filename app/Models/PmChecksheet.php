<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmChecksheet extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'checksheet_code',
        'checksheet_name',
        'description',
        'is_active',
        'created_by',
        'assignment_history_known',
        'first_observed_machine_assignment_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'assignment_history_known' => 'boolean',
            'first_observed_machine_assignment_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function machineAssignments(): HasMany
    {
        return $this->hasMany(PmChecksheetMachine::class);
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
