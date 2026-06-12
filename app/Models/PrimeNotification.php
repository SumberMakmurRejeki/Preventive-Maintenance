<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrimeNotification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'notification_type',
        'title',
        'message',
        'target_role',
        'target_user_id',
        'related_table',
        'related_id',
        'target_url',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NotificationRead::class, 'notification_id');
    }

    public function scopeByStatus(Builder $query, string $notificationType): void
    {
        $query->where('notification_type', $notificationType);
    }
}
