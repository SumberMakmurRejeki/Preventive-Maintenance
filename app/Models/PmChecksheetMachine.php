<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmChecksheetMachine extends Model
{
    use HasFactory;

    protected $fillable = [
        'pm_checksheet_id',
        'machine_id',
        'assigned_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    public function checksheet(): BelongsTo
    {
        return $this->belongsTo(PmChecksheet::class, 'pm_checksheet_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(PmChecksheetPart::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(PmSchedule::class);
    }
}
