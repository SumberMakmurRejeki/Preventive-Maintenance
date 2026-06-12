<?php

namespace App\Jobs;

use App\Models\PrimeNotification;
use App\Models\User;
use App\Notifications\AdminBrowserPushNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class DispatchAdminWebPushJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $notificationId,
    ) {}

    public function handle(): void
    {
        $notification = PrimeNotification::query()->find($this->notificationId);

        if (! $notification) {
            return;
        }

        $admins = User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->when(
                $notification->target_user_id !== null,
                fn ($query) => $query->whereKey($notification->target_user_id),
            )
            ->with('pushSubscriptions')
            ->get()
            ->filter(fn (User $admin): bool => $admin->pushSubscriptions->isNotEmpty());

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new AdminBrowserPushNotification($notification));
    }
}
