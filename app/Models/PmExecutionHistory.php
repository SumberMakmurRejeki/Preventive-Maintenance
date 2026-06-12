<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmExecutionHistory extends Model
{
    use HasFactory;

    protected $table = 'pm_execution_history';

    public $timestamps = false;

    protected $fillable = [
        'pm_execution_id',
        'changed_by',
        'changed_at',
        'field_name',
        'old_value',
        'new_value',
        'change_note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(PmExecution::class, 'pm_execution_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): void
    {
        $query->whereBetween('changed_at', [$startDate, $endDate]);
    }
}
