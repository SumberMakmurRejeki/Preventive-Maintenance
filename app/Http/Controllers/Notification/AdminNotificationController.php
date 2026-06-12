<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Services\Notification\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminNotificationController extends Controller
{
    public function __construct(
        protected AdminNotificationService $notificationService,
    ) {
    }

    public function index(): JsonResponse
    {
        /** @var \App\Models\User $admin */
        $admin = Auth::user();

        return response()->json($this->notificationService->listForAdmin($admin));
    }

    public function markAsRead(int $notificationId): JsonResponse
    {
        /** @var \App\Models\User $admin */
        $admin = Auth::user();

        $notification = $this->notificationService->markAsRead($notificationId, $admin);
        $redirectUrl = $this->notificationService->resolveRedirectUrl($notification);

        return response()->json([
            'ok' => $notification !== null,
            'redirect_url' => $redirectUrl,
            'unread_count' => $this->notificationService->unreadCount($admin),
        ]);
    }

    public function markAllAsRead(): JsonResponse
    {
        /** @var \App\Models\User $admin */
        $admin = Auth::user();

        $updated = $this->notificationService->markAllAsRead($admin);

        return response()->json([
            'ok' => true,
            'updated' => $updated,
            'unread_count' => 0,
        ]);
    }

    public function go(int $notificationId): RedirectResponse
    {
        /** @var \App\Models\User $admin */
        $admin = Auth::user();

        $notification = $this->notificationService->markAsRead($notificationId, $admin);
        $redirectUrl = $this->notificationService->resolveRedirectUrl($notification);

        return redirect()->to($redirectUrl);
    }
}
