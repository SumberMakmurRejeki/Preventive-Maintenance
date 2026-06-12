<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmExecutionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pm_execution_id',
        'pm_checksheet_part_id',
        'pm_checksheet_standard_id',
        'part_name_snapshot',
        'standard_name_snapshot',
        'input_type_snapshot',
        'action_options_snapshot',
        'target_value_snapshot',
        'min_value_snapshot',
        'max_value_snapshot',
        'unit_snapshot',
        'action_value',
        'number_value',
        'is_warning',
        'warning_message',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'action_options_snapshot' => 'array',
            'target_value_snapshot' => 'decimal:2',
            'min_value_snapshot' => 'decimal:2',
            'max_value_snapshot' => 'decimal:2',
            'number_value' => 'decimal:2',
            'is_warning' => 'boolean',
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(PmExecution::class, 'pm_execution_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(PmChecksheetPart::class, 'pm_checksheet_part_id');
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(PmChecksheetStandard::class, 'pm_checksheet_standard_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(PmExecutionMedia::class);
    }

    public function scopeByStatus(Builder $query, bool $isWarning): void
    {
        $query->where('is_warning', $isWarning);
    }
}
