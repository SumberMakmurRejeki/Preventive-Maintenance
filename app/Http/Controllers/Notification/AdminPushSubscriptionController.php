<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\DeletePushSubscriptionRequest;
use App\Http\Requests\Notification\StorePushSubscriptionRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AdminPushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        /** @var User $admin */
        $admin = Auth::user();

        $validated = $request->validated();

        $admin->updatePushSubscription(
            endpoint: (string) $validated['endpoint'],
            key: $validated['public_key'] !== null ? (string) $validated['public_key'] : null,
            token: $validated['auth_token'] !== null ? (string) $validated['auth_token'] : null,
            contentEncoding: $validated['content_encoding'] !== null ? (string) $validated['content_encoding'] : null,
        );

        return response()->json([
            'ok' => true,
            'status' => 'subscribed',
        ]);
    }

    public function destroy(DeletePushSubscriptionRequest $request): JsonResponse
    {
        /** @var User $admin */
        $admin = Auth::user();

        $admin->deletePushSubscription((string) $request->validated('endpoint'));

        return response()->json([
            'ok' => true,
            'status' => 'unsubscribed',
        ]);
    }
}
