<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GuestLoginRequest;
use App\Models\GuestSession;
use App\Services\Auth\ActivityLogService;
use Illuminate\Http\RedirectResponse;

class GuestLoginController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLog,
    ) {
    }

    public function store(GuestLoginRequest $request): RedirectResponse
    {
        $request->session()->regenerate();

        $guestSession = GuestSession::query()->create([
            'guest_name' => $request->validated('guest_name'),
            'session_id' => $request->session()->getId(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_at' => now(),
        ]);

        $request->session()->put([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ]);
        $request->session()->forget('redirect_to');

        $this->activityLog->log(
            request: $request,
            moduleName: 'auth',
            action: 'login',
            description: 'Guest login success',
            tableName: 'guest_sessions',
            recordId: $guestSession->id,
        );

        return redirect()->route('dashboard');
    }
}
