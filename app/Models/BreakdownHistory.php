<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakdownHistory extends Model
{
    use HasFactory;

    protected $table = 'breakdown_history';

    public $timestamps = false;

    protected $fillable = [
        'breakdown_id',
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

    public function breakdown(): BelongsTo
    {
        return $this->belongsTo(Breakdown::class);
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
