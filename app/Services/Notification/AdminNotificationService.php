<?php

namespace App\Services\Notification;

use App\Jobs\DispatchAdminWebPushJob;
use App\Models\NotificationRead;
use App\Models\PrimeNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class AdminNotificationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createUnique(
        string $notificationType,
        string $title,
        string $message,
        ?string $relatedTable,
        ?int $relatedId,
        ?string $targetUrl,
        array $data = [],
        ?int $targetUserId = null,
    ): PrimeNotification {
        $notification = PrimeNotification::query()
            ->where('notification_type', $notificationType)
            ->where('related_table', $relatedTable)
            ->where('related_id', $relatedId)
            ->where('target_user_id', $targetUserId)
            ->first();

        if ($notification) {
            return $notification;
        }

        $notification = PrimeNotification::query()->create([
            'notification_type' => $notificationType,
            'title' => $title,
            'message' => $message,
            'target_role' => 'admin',
            'target_user_id' => $targetUserId,
            'related_table' => $relatedTable,
            'related_id' => $relatedId,
            'target_url' => $targetUrl,
            'data' => $data,
        ]);

        DispatchAdminWebPushJob::dispatch($notification->id)->afterCommit();

        return $notification;
    }

    /**
     * @return array{
     *   unread_count:int,
     *   notifications:array<int, array<string, mixed>>
     * }
     */
    public function listForAdmin(User $admin, int $limit = 10): array
    {
        $notifications = PrimeNotification::query()
            ->where('target_role', 'admin')
            ->where(function ($query) use ($admin): void {
                $query->whereNull('target_user_id')
                    ->orWhere('target_user_id', $admin->id);
            })
            ->with([
                'reads' => fn ($query) => $query
                    ->where('user_id', $admin->id)
                    ->select(['id', 'notification_id', 'read_at', 'user_id']),
            ])
            ->latest('id')
            ->limit($limit)
            ->get();

        return [
            'unread_count' => $this->unreadCount($admin),
            'notifications' => $notifications->map(function (PrimeNotification $notification): array {
                $read = $notification->reads->first();

                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'notification_type' => $notification->notification_type,
                    'target_url' => $notification->target_url,
                    'data' => $notification->data,
                    'created_at' => optional($notification->created_at)?->toIso8601String(),
                    'created_at_human' => optional($notification->created_at)?->diffForHumans(),
                    'is_read' => $read !== null,
                ];
            })->all(),
        ];
    }

    public function unreadCount(User $admin): int
    {
        $readNotificationIds = NotificationRead::query()
            ->where('user_id', $admin->id)
            ->pluck('notification_id');

        return PrimeNotification::query()
            ->where('target_role', 'admin')
            ->where(function ($query) use ($admin): void {
                $query->whereNull('target_user_id')
                    ->orWhere('target_user_id', $admin->id);
            })
            ->whereNotIn('id', $readNotificationIds)
            ->count();
    }

    public function markAsRead(int $notificationId, User $admin): ?PrimeNotification
    {
        $notification = PrimeNotification::query()
            ->whereKey($notificationId)
            ->where('target_role', 'admin')
            ->where(function ($query) use ($admin): void {
                $query->whereNull('target_user_id')
                    ->orWhere('target_user_id', $admin->id);
            })
            ->first();

        if (! $notification) {
            return null;
        }

        NotificationRead::query()->firstOrCreate(
            [
                'notification_id' => $notification->id,
                'user_id' => $admin->id,
            ],
            [
                'read_at' => now(),
            ],
        );

        return $notification;
    }

    public function markAllAsRead(User $admin): int
    {
        $notifications = PrimeNotification::query()
            ->where('target_role', 'admin')
            ->where(function ($query) use ($admin): void {
                $query->whereNull('target_user_id')
                    ->orWhere('target_user_id', $admin->id);
            })
            ->select('id')
            ->get();

        $existingReadIds = NotificationRead::query()
            ->where('user_id', $admin->id)
            ->pluck('notification_id');

        $toInsert = $notifications
            ->reject(fn (PrimeNotification $notification): bool => $existingReadIds->contains($notification->id))
            ->map(fn (PrimeNotification $notification): array => [
                'notification_id' => $notification->id,
                'user_id' => $admin->id,
                'read_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        if ($toInsert === []) {
            return 0;
        }

        NotificationRead::query()->insert($toInsert);

        return count($toInsert);
    }

    public function resolveRedirectUrl(?PrimeNotification $notification): string
    {
        if (! $notification || ! $notification->target_url) {
            return route('dashboard');
        }

        $targetUrl = (string) $notification->target_url;

        if (! str_starts_with($targetUrl, '/')) {
            return route('dashboard');
        }

        try {
            Route::getRoutes()->match(Request::create($targetUrl, 'GET'));

            return $targetUrl;
        } catch (\Throwable) {
            return route('dashboard');
        }
    }
}
