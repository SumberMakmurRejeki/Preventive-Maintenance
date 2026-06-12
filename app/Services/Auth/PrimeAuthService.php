<?php

namespace App\Services\Auth;

use App\Models\GuestSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PrimeAuthService
{
    public function authenticatedUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public function guestSession(Request $request): ?GuestSession
    {
        $guestSessionId = $request->session()->get('guest_session_id');

        if (! $guestSessionId) {
            return null;
        }

        return GuestSession::query()
            ->whereKey($guestSessionId)
            ->whereNull('logout_at')
            ->first();
    }

    public function role(Request $request): ?string
    {
        if ($user = $this->authenticatedUser()) {
            return $user->role;
        }

        return $this->guestSession($request) ? 'guest' : null;
    }

    public function name(Request $request): ?string
    {
        if ($user = $this->authenticatedUser()) {
            return $user->name;
        }

        return $this->guestSession($request)?->guest_name;
    }

    public function isAuthenticated(Request $request): bool
    {
        return $this->role($request) !== null;
    }

    public function logoutGuest(Request $request): void
    {
        $guestSession = $this->guestSession($request);

        if ($guestSession) {
            $guestSession->forceFill([
                'logout_at' => now(),
            ])->save();
        }

        $request->session()->forget([
            'guest_session_id',
            'guest_name',
        ]);
    }
}
