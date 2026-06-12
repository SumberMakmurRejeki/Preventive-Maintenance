<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmChecksheetPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'pm_checksheet_machine_id',
        'part_name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function checksheetMachine(): BelongsTo
    {
        return $this->belongsTo(PmChecksheetMachine::class, 'pm_checksheet_machine_id');
    }

    public function standards(): HasMany
    {
        return $this->hasMany(PmChecksheetStandard::class);
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
