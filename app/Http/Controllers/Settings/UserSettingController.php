<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ResetUserSettingPasswordRequest;
use App\Http\Requests\Settings\StoreUserSettingRequest;
use App\Http\Requests\Settings\UpdateUserSettingRequest;
use App\Models\User;
use App\Services\Auth\PrimeAuthService;
use App\Services\Settings\UserSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class UserSettingController extends Controller
{
    public function __construct(
        protected UserSettingService $userSettingService,
        protected PrimeAuthService $primeAuth,
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());
        $role = $request->string('role')->toString();
        $status = $request->string('status')->toString();

        $loadingError = null;

        try {
            $users = User::query()
                ->whereIn('role', ['admin', 'operator'])
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($subQuery) use ($search): void {
                        $subQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
                })
                ->when(in_array($role, ['admin', 'operator'], true), fn ($query) => $query->where('role', $role))
                ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString();
        } catch (Throwable) {
            $users = User::query()->whereRaw('1 = 0')->paginate(10);
            $loadingError = 'Data user gagal dimuat. Silakan coba lagi.';
        }

        $editingUser = null;
        $resetPasswordUser = null;

        $modalAction = old('modal_action');

        if ($modalAction === 'edit' && old('user_id')) {
            $editingUser = User::query()->find(old('user_id'));
        }

        if ($modalAction === 'reset_password' && old('user_id')) {
            $resetPasswordUser = User::query()->find(old('user_id'));
        }

        return view('pages.settings.users.index', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $role,
                'status' => $status,
            ],
            'loadingError' => $loadingError,
            'modalState' => [
                'action' => $modalAction,
                'user_id' => old('user_id'),
            ],
            'editingUser' => $editingUser,
            'resetPasswordUser' => $resetPasswordUser,
        ]);
    }

    public function store(StoreUserSettingRequest $request): RedirectResponse
    {
        $this->userSettingService->create($request, $request->validated());

        return redirect()
            ->route('settings-users.index')
            ->with('flash_success', 'User berhasil ditambahkan.');
    }

    public function update(UpdateUserSettingRequest $request, int $userId): RedirectResponse
    {
        $result = $this->userSettingService->update($request, $userId, $request->validated());

        if (! $result['found']) {
            return redirect()->route('settings-users.index')->with('flash_error', 'User tidak ditemukan.');
        }

        return redirect()
            ->route('settings-users.index')
            ->with('flash_success', 'Data user berhasil diperbarui.');
    }

    public function resetPassword(ResetUserSettingPasswordRequest $request, int $userId): RedirectResponse
    {
        $result = $this->userSettingService->resetPassword($request, $userId, $request->validated());

        if (! $result['found']) {
            return redirect()->route('settings-users.index')->with('flash_error', 'User tidak ditemukan.');
        }

        return redirect()
            ->route('settings-users.index')
            ->with('flash_success', 'Password user berhasil diperbarui.');
    }

    public function deactivate(Request $request, int $userId): RedirectResponse
    {
        $result = $this->userSettingService->deactivate($request, $userId);

        if (! $result['found']) {
            return redirect()->route('settings-users.index')->with('flash_error', 'User tidak ditemukan.');
        }

        return redirect()
            ->route('settings-users.index')
            ->with('flash_success', 'User berhasil dinonaktifkan.');
    }

    public function activate(Request $request, int $userId): RedirectResponse
    {
        $result = $this->userSettingService->activate($request, $userId);

        if (! $result['found']) {
            return redirect()->route('settings-users.index')->with('flash_error', 'User tidak ditemukan.');
        }

        return redirect()
            ->route('settings-users.index')
            ->with('flash_success', 'User berhasil diaktifkan.');
    }

    public function destroy(Request $request, int $userId): RedirectResponse
    {
        $result = $this->userSettingService->delete($request, $userId);

        if (! $result['found']) {
            return redirect()->route('settings-users.index')->with('flash_error', 'User tidak ditemukan.');
        }

        return redirect()
            ->route('settings-users.index')
            ->with('flash_success', 'User berhasil dihapus.');
    }
}
