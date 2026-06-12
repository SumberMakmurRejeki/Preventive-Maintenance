<?php

namespace App\Services\Settings;

use App\Models\User;
use App\Services\Auth\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class UserSettingService
{
    public function __construct(
        protected ActivityLogService $activityLog,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Request $request, array $payload): User
    {
        $user = User::query()->create($this->normalizeUpsertPayload($payload, true));

        $this->activityLog->log(
            request: $request,
            moduleName: 'pengaturan_user',
            action: 'create',
            description: sprintf('Tambah user %s', $user->username),
            tableName: 'users',
            recordId: $user->id,
            newValues: $user->only(['name', 'username', 'role', 'is_active']),
        );

        return $user;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{found: bool}
     */
    public function update(Request $request, int $userId, array $payload): array
    {
        $user = $this->findManagedUser($userId);

        if (! $user) {
            return ['found' => false];
        }

        $oldValues = $user->only(['name', 'username', 'role', 'is_active']);

        $user->fill($this->normalizeUpsertPayload($payload, false));
        $user->save();

        $this->activityLog->log(
            request: $request,
            moduleName: 'pengaturan_user',
            action: 'update',
            description: sprintf('Edit user %s', $user->username),
            tableName: 'users',
            recordId: $user->id,
            oldValues: $oldValues,
            newValues: $user->only(['name', 'username', 'role', 'is_active']),
        );

        return ['found' => true];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{found: bool}
     */
    public function resetPassword(Request $request, int $userId, array $payload): array
    {
        $user = $this->findManagedUser($userId);

        if (! $user) {
            return ['found' => false];
        }

        $user->forceFill([
            'password' => Hash::make((string) $payload['new_password']),
        ])->save();

        $this->activityLog->log(
            request: $request,
            moduleName: 'pengaturan_user',
            action: 'reset_password',
            description: sprintf('Reset password user %s', $user->username),
            tableName: 'users',
            recordId: $user->id,
        );

        return ['found' => true];
    }

    /**
     * @return array{found: bool}
     */
    public function deactivate(Request $request, int $userId): array
    {
        $user = $this->findManagedUser($userId);

        if (! $user) {
            return ['found' => false];
        }

        $oldValues = $user->only(['name', 'username', 'role', 'is_active']);
        $user->forceFill(['is_active' => false])->save();

        $this->activityLog->log(
            request: $request,
            moduleName: 'pengaturan_user',
            action: 'deactivate',
            description: sprintf('Nonaktifkan user %s', $user->username),
            tableName: 'users',
            recordId: $user->id,
            oldValues: $oldValues,
            newValues: $user->only(['name', 'username', 'role', 'is_active']),
        );

        return ['found' => true];
    }

    /**
     * @return array{found: bool}
     */
    public function activate(Request $request, int $userId): array
    {
        $user = $this->findManagedUser($userId);

        if (! $user) {
            return ['found' => false];
        }

        $oldValues = $user->only(['name', 'username', 'role', 'is_active']);
        $user->forceFill(['is_active' => true])->save();

        $this->activityLog->log(
            request: $request,
            moduleName: 'pengaturan_user',
            action: 'activate',
            description: sprintf('Aktifkan user %s', $user->username),
            tableName: 'users',
            recordId: $user->id,
            oldValues: $oldValues,
            newValues: $user->only(['name', 'username', 'role', 'is_active']),
        );

        return ['found' => true];
    }

    /**
     * @return array{found: bool}
     */
    public function delete(Request $request, int $userId): array
    {
        $user = $this->findManagedUser($userId);

        if (! $user) {
            return ['found' => false];
        }

        $oldValues = $user->only(['name', 'username', 'role', 'is_active']);
        $recordId = $user->id;
        $username = $user->username;

        $this->activityLog->log(
            request: $request,
            moduleName: 'pengaturan_user',
            action: 'delete',
            description: sprintf('Delete user %s', $username),
            tableName: 'users',
            recordId: $recordId,
            oldValues: $oldValues,
        );

        $user->forceDelete();

        return ['found' => true];
    }

    protected function findManagedUser(int $userId): ?User
    {
        return User::query()
            ->whereKey($userId)
            ->whereIn('role', ['admin', 'operator'])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeUpsertPayload(array $payload, bool $withPassword): array
    {
        $normalized = [
            'name' => trim((string) ($payload['name'] ?? '')),
            'username' => strtolower(trim((string) ($payload['username'] ?? ''))),
            'role' => strtolower(trim((string) ($payload['role'] ?? ''))),
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ];

        if ($withPassword) {
            $normalized['password'] = (string) ($payload['password'] ?? '');
        }

        return Arr::only($normalized, ['name', 'username', 'password', 'role', 'is_active']);
    }
}
