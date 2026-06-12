<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmChecksheetStandard extends Model
{
    use HasFactory;

    protected $fillable = [
        'pm_checksheet_part_id',
        'standard_name',
        'input_type',
        'action_options',
        'target_value',
        'min_value',
        'max_value',
        'unit',
        'description',
        'is_required',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'action_options' => 'array',
            'target_value' => 'decimal:2',
            'min_value' => 'decimal:2',
            'max_value' => 'decimal:2',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(PmChecksheetPart::class, 'pm_checksheet_part_id');
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
