<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasPushSubscriptions;
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class);
    }

    public function createdChecksheets(): HasMany
    {
        return $this->hasMany(PmChecksheet::class, 'created_by');
    }

    public function createdChecksheetMachines(): HasMany
    {
        return $this->hasMany(PmChecksheetMachine::class, 'created_by');
    }

    public function createdSchedules(): HasMany
    {
        return $this->hasMany(PmSchedule::class, 'created_by');
    }

    public function pmExecutions(): HasMany
    {
        return $this->hasMany(PmExecution::class, 'operator_id');
    }

    public function approvedExecutions(): HasMany
    {
        return $this->hasMany(PmExecution::class, 'approved_by');
    }

    public function uploadedPmMedia(): HasMany
    {
        return $this->hasMany(PmExecutionMedia::class, 'uploaded_by');
    }

    public function pmExecutionHistoryChanges(): HasMany
    {
        return $this->hasMany(PmExecutionHistory::class, 'changed_by');
    }

    public function createdBreakdowns(): HasMany
    {
        return $this->hasMany(Breakdown::class, 'created_by');
    }

    public function closedBreakdowns(): HasMany
    {
        return $this->hasMany(Breakdown::class, 'closed_by');
    }

    public function uploadedBreakdownMedia(): HasMany
    {
        return $this->hasMany(BreakdownMedia::class, 'uploaded_by');
    }

    public function breakdownHistoryChanges(): HasMany
    {
        return $this->hasMany(BreakdownHistory::class, 'changed_by');
    }

    public function targetedNotifications(): HasMany
    {
        return $this->hasMany(PrimeNotification::class, 'target_user_id');
    }

    public function notificationReads(): HasMany
    {
        return $this->hasMany(NotificationRead::class);
    }

    public function requestedExports(): HasMany
    {
        return $this->hasMany(ReportExport::class, 'requested_by');
    }
}
