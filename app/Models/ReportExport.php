<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_type',
        'file_type',
        'file_name',
        'file_path',
        'filter_data',
        'status',
        'requested_by',
        'requested_by_name_snapshot',
        'requested_at',
        'completed_at',
        'failed_message',
    ];

    protected function casts(): array
    {
        return [
            'filter_data' => 'array',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function scopeByStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): void
    {
        $query->whereBetween('requested_at', [$startDate, $endDate]);
    }
}
