<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakdownMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'breakdown_id',
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

    public function breakdown(): BelongsTo
    {
        return $this->belongsTo(Breakdown::class);
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
