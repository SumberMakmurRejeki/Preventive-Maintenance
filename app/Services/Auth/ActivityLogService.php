<?php

namespace App\Services\Auth;

use App\Models\UserActivityLog;
use Illuminate\Http\Request;

class ActivityLogService
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        Request $request,
        string $moduleName,
        string $action,
        ?string $description = null,
        ?string $tableName = null,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        $user = $this->primeAuth->authenticatedUser();
        $guestSession = $this->primeAuth->guestSession($request);

        UserActivityLog::query()->create([
            'user_id' => $user?->id,
            'guest_session_id' => $guestSession?->id,
            'actor_type' => $user ? 'user' : ($guestSession ? 'guest' : 'system'),
            'actor_name_snapshot' => $user?->name ?? $guestSession?->guest_name,
            'module_name' => $moduleName,
            'action' => $action,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
