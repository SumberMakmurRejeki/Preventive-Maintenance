<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmExecutionMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'pm_execution_id',
        'pm_execution_item_id',
        'pm_checksheet_part_id',
        'part_name_snapshot',
        'file_type',
        'file_path',
        'original_file_path',
        'file_name',
        'mime_type',
        'file_size',
        'original_file_size',
        'compressed_file_size',
        'note',
        'uploaded_by',
        'uploaded_by_name_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'original_file_size' => 'integer',
            'compressed_file_size' => 'integer',
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(PmExecution::class, 'pm_execution_id');
    }

    public function executionItem(): BelongsTo
    {
        return $this->belongsTo(PmExecutionItem::class, 'pm_execution_item_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(PmChecksheetPart::class, 'pm_checksheet_part_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeByStatus(Builder $query, string $fileType): void
    {
        $query->where('file_type', $fileType);
    }
}
