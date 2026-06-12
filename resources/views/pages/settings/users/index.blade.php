<x-layouts.app
    title="Pengaturan User PRIME"
    heading="Pengaturan User"
    :actor-name="$actorName"
    :role="$role"
>
    @php
        $editingUserData = $editingUser;
        $resetPasswordUserData = $resetPasswordUser;
    @endphp

    <section class="space-y-6" data-user-settings-page data-open-modal="{{ $modalState['action'] ?? '' }}">
        <div class="flex items-center gap-2 text-[15px] font-medium text-[rgba(0,0,0,0.95)]">
            <span>Settings</span>
            <span class="text-[#a39e98]">/</span>
            <span>Pengaturan User</span>
        </div>

        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-[2.35rem] font-semibold tracking-[-0.05em] text-[var(--color-prime-ink)]">Pengaturan User</h2>
                <p class="mt-2 text-lg text-[var(--color-prime-muted)]">Kelola akun Admin dan Operator yang dapat mengakses sistem PRIME.</p>
            </div>

            <x-ui.button size="lg" data-user-create-open>
                Tambah User
            </x-ui.button>
        </div>

        @if (session('flash_success'))
            <x-ui.alert variant="success">
                {{ session('flash_success') }}
            </x-ui.alert>
        @endif

        @if (session('flash_error'))
            <x-ui.alert variant="error">
                {{ session('flash_error') }}
            </x-ui.alert>
        @endif

        <x-ui.card padding="p-0" class="overflow-hidden">
            <div class="border-b border-[var(--color-prime-border)] px-5 py-5 sm:px-6">
                <form method="GET" class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
                        <label class="relative block min-w-0 lg:w-[25rem]">
                            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-[var(--color-prime-muted)]">
                                <x-ui.icon name="magnifying-glass" class="h-5 w-5" />
                            </span>
                            <input
                                type="search"
                                name="search"
                                value="{{ $filters['search'] }}"
                                placeholder="Cari nama atau username..."
                                class="w-full rounded-xl border border-[var(--color-prime-border)] bg-white py-3.5 pl-12 pr-4 text-base text-[var(--color-prime-ink)] outline-none transition placeholder:text-[var(--color-prime-muted)] focus:border-[var(--color-prime-primary)] focus:ring-4 focus:ring-[var(--color-prime-primary)]/10"
                            >
                        </label>

                        <label class="block lg:w-[11rem]">
                            <select
                                name="role"
                                class="w-full rounded-xl border border-[var(--color-prime-border)] bg-white px-4 py-3.5 text-base text-[var(--color-prime-ink)] outline-none transition focus:border-[var(--color-prime-primary)] focus:ring-4 focus:ring-[var(--color-prime-primary)]/10"
                            >
                                <option value="">Semua Role</option>
                                <option value="admin" @selected($filters['role'] === 'admin')>Admin</option>
                                <option value="operator" @selected($filters['role'] === 'operator')>Operator</option>
                            </select>
                        </label>

                        <label class="block lg:w-[11rem]">
                            <select
                                name="status"
                                class="w-full rounded-xl border border-[var(--color-prime-border)] bg-white px-4 py-3.5 text-base text-[var(--color-prime-ink)] outline-none transition focus:border-[var(--color-prime-primary)] focus:ring-4 focus:ring-[var(--color-prime-primary)]/10"
                            >
                                <option value="">Semua Status</option>
                                <option value="active" @selected($filters['status'] === 'active')>Aktif</option>
                                <option value="inactive" @selected($filters['status'] === 'inactive')>Nonaktif</option>
                            </select>
                        </label>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-ui.button type="submit" variant="ghost">Terapkan Filter</x-ui.button>
                        <a href="{{ route('settings-users.index') }}" class="text-base font-medium text-[var(--color-prime-muted)] transition hover:text-[var(--color-prime-ink)]">
                            Reset Filter
                        </a>
                    </div>
                </form>
            </div>

            <div data-user-loading-state class="hidden px-6 py-8">
                <div class="flex min-h-40 flex-col items-center justify-center rounded-[1.35rem] border border-dashed border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] px-6 py-10 text-center">
                    <span class="h-8 w-8 animate-spin rounded-full border-2 border-[var(--color-prime-border)] border-t-[var(--color-prime-primary)]"></span>
                    <p class="mt-4 text-sm text-[var(--color-prime-muted)]">Memuat data user...</p>
                </div>
            </div>

            @if ($loadingError)
                <div class="px-6 py-8">
                    <x-ui.alert variant="error">
                        {{ $loadingError }}
                    </x-ui.alert>
                </div>
            @elseif ($users->count() === 0)
                <div class="px-6 py-8">
                    @if ($filters['search'] !== '' || $filters['role'] !== '' || $filters['status'] !== '')
                        <x-ui.empty-state
                            title="User tidak ditemukan"
                            message="Tidak ada user yang sesuai dengan pencarian atau filter yang dipilih."
                        />
                    @else
                        <x-ui.empty-state
                            title="Belum ada user"
                            message="Tambahkan user Admin atau Operator untuk mulai menggunakan sistem."
                        />
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[var(--color-prime-border)]">
                        <thead class="bg-[var(--color-prime-warm-white)]">
                            <tr>
                                <th class="px-5 py-5 text-left text-[14px] font-medium tracking-normal text-[var(--color-prime-muted)] sm:px-6">No</th>
                                <th class="px-5 py-5 text-left text-[14px] font-medium tracking-normal text-[var(--color-prime-muted)] sm:px-6">
                                    <button type="button" class="inline-flex items-center gap-1" data-sort-trigger data-sort-group="users" data-sort-field="name" aria-label="Urutkan Nama">
                                        Nama
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="users" data-sort-field="name">↕</span>
                                    </button>
                                </th>
                                <th class="px-5 py-5 text-left text-[14px] font-medium tracking-normal text-[var(--color-prime-muted)] sm:px-6">
                                    <button type="button" class="inline-flex items-center gap-1" data-sort-trigger data-sort-group="users" data-sort-field="username" aria-label="Urutkan Username">
                                        Username
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="users" data-sort-field="username">↕</span>
                                    </button>
                                </th>
                                <th class="px-5 py-5 text-left text-[14px] font-medium tracking-normal text-[var(--color-prime-muted)] sm:px-6">Role</th>
                                <th class="px-5 py-5 text-left text-[14px] font-medium tracking-normal text-[var(--color-prime-muted)] sm:px-6">Status</th>
                                <th class="px-5 py-5 text-left text-[14px] font-medium tracking-normal text-[var(--color-prime-muted)] sm:px-6">Last Login</th>
                                <th class="px-5 py-5 text-center text-[14px] font-medium tracking-normal text-[var(--color-prime-muted)] sm:px-6">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--color-prime-border)] bg-white">
                            @foreach ($users as $user)
                                <tr class="align-middle" data-user-row data-name="{{ strtolower($user->name) }}" data-username="{{ strtolower($user->username) }}">
                                    <td class="px-5 py-7 text-lg text-[var(--color-prime-ink)] sm:px-6">{{ $users->firstItem() + $loop->index }}</td>
                                    <td class="px-5 py-7 text-[1.1rem] font-semibold text-[var(--color-prime-ink)] sm:px-6">{{ $user->name }}</td>
                                    <td class="px-5 py-7 text-[1.08rem] text-[var(--color-prime-ink)] sm:px-6">{{ '@' . $user->username }}</td>
                                    <td class="px-5 py-7 sm:px-6">
                                        <x-ui.badge :variant="$user->role === 'admin' ? 'info' : 'neutral'" class="rounded-full px-4 py-2 text-base">
                                            {{ $user->role === 'admin' ? 'Admin' : 'Operator' }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="px-5 py-7 sm:px-6">
                                        <x-ui.badge :variant="$user->is_active ? 'success' : 'warning'" class="rounded-full px-4 py-2 text-base">
                                            {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="px-5 py-7 text-[1.05rem] text-[var(--color-prime-muted)] sm:px-6">
                                        {{ $user->last_login_at?->format('Y-m-d H:i') ?? '-' }}
                                    </td>
                                    <td class="px-5 py-7 sm:px-6">
                                        <div class="flex items-center justify-center gap-2">
                                            <button
                                                type="button"
                                                data-user-edit-open
                                                data-user-id="{{ $user->id }}"
                                                data-update-url="{{ route('settings-users.update', $user->id) }}"
                                                data-user-name="{{ $user->name }}"
                                                data-user-username="{{ $user->username }}"
                                                data-user-role="{{ $user->role }}"
                                                data-user-active="{{ $user->is_active ? 1 : 0 }}"
                                                class="rounded-xl p-2 text-[var(--color-prime-muted)] transition hover:bg-[var(--color-prime-soft)] hover:text-[var(--color-prime-primary)]"
                                                aria-label="Edit user {{ $user->name }}"
                                            >
                                                <x-ui.icon name="pencil-square" class="h-5 w-5" />
                                            </button>

                                            <button
                                                type="button"
                                                data-user-reset-open
                                                data-user-id="{{ $user->id }}"
                                                data-reset-url="{{ route('settings-users.reset-password', $user->id) }}"
                                                data-user-name="{{ $user->name }}"
                                                data-user-username="{{ $user->username }}"
                                                class="rounded-xl p-2 text-[var(--color-prime-muted)] transition hover:bg-[var(--color-prime-soft)] hover:text-[var(--color-prime-warning)]"
                                                aria-label="Reset password user {{ $user->name }}"
                                            >
                                                <x-ui.icon name="support" class="h-5 w-5" />
                                            </button>

                                            @if ($user->is_active)
                                                <button
                                                    type="button"
                                                    data-user-status-open
                                                    data-status-url="{{ route('settings-users.deactivate', $user->id) }}"
                                                    data-status-label="Nonaktifkan User"
                                                    data-status-description="User {{ $user->name }} tidak akan bisa login setelah dinonaktifkan."
                                                    data-status-button="Nonaktifkan"
                                                    data-status-variant="warning"
                                                    class="rounded-xl p-2 text-[var(--color-prime-muted)] transition hover:bg-[var(--color-prime-soft)] hover:text-[var(--color-prime-warning)]"
                                                    aria-label="Nonaktifkan user {{ $user->name }}"
                                                >
                                                    <x-ui.icon name="power" class="h-5 w-5" />
                                                </button>
                                            @else
                                                <button
                                                    type="button"
                                                    data-user-status-open
                                                    data-status-url="{{ route('settings-users.activate', $user->id) }}"
                                                    data-status-label="Aktifkan User"
                                                    data-status-description="User {{ $user->name }} akan bisa login kembali setelah diaktifkan."
                                                    data-status-button="Aktifkan"
                                                    data-status-variant="success"
                                                    class="rounded-xl p-2 text-[var(--color-prime-muted)] transition hover:bg-[var(--color-prime-soft)] hover:text-[var(--color-prime-success)]"
                                                    aria-label="Aktifkan user {{ $user->name }}"
                                                >
                                                    <x-ui.icon name="play" class="h-5 w-5" />
                                                </button>
                                            @endif

                                            <button
                                                type="button"
                                                data-user-delete-open
                                                data-delete-url="{{ route('settings-users.destroy', $user->id) }}"
                                                data-user-name="{{ $user->name }}"
                                                class="rounded-xl p-2 text-[var(--color-prime-muted)] transition hover:bg-[var(--color-prime-soft)] hover:text-[var(--color-prime-danger)]"
                                                aria-label="Delete user {{ $user->name }}"
                                            >
                                                <x-ui.icon name="trash" class="h-5 w-5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-ui.numeric-pagination
                    :paginator="$users"
                    summary-class="text-lg text-[var(--color-prime-muted)]"
                    container-class="flex flex-col gap-4 border-t border-[var(--color-prime-border)] px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between"
                />
            @endif
        </x-ui.card>
    </section>

    <x-ui.modal id="user-create-modal" title="Tambah User">
        <form action="{{ route('settings-users.store') }}" method="POST" class="space-y-5">
            @csrf
            <input type="hidden" name="modal_action" value="create">

            <div>
                <label class="mb-2 block text-base font-semibold text-[var(--color-prime-ink)]">Nama <span class="text-[var(--color-prime-danger)]">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3.5 text-base" />
                @if (($modalState['action'] ?? null) === 'create') @error('name')<p class="mt-2 text-sm text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror @endif
            </div>

            <div>
                <label class="mb-2 block text-base font-semibold text-[var(--color-prime-ink)]">Username <span class="text-[var(--color-prime-danger)]">*</span></label>
                <input type="text" name="username" value="{{ old('username') }}" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3.5 text-base" />
                @if (($modalState['action'] ?? null) === 'create') @error('username')<p class="mt-2 text-sm text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror @endif
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-base font-semibold text-[var(--color-prime-ink)]">Password <span class="text-[var(--color-prime-danger)]">*</span></label>
                    <input type="password" name="password" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3.5 text-base" />
                    @if (($modalState['action'] ?? null) === 'create') @error('password')<p class="mt-2 text-sm text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror @endif
                </div>
                <div>
                    <label class="mb-2 block text-base font-semibold text-[var(--color-prime-ink)]">Konfirmasi Password <span class="text-[var(--color-prime-danger)]">*</span></label>
                    <input type="password" name="password_confirmation" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3.5 text-base" />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-base font-semibold text-[var(--color-prime-ink)]">Role <span class="text-[var(--color-prime-danger)]">*</span></label>
                    <select name="role" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3.5 text-base">
                        <option value="">Pilih Role</option>
                        <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                        <option value="operator" @selected(old('role') === 'operator')>Operator</option>
                    </select>
                    @if (($modalState['action'] ?? null) === 'create') @error('role')<p class="mt-2 text-sm text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror @endif
                </div>
                <div>
                    <label class="mb-2 block text-base font-semibold text-[var(--color-prime-ink)]">Status <span class="text-[var(--color-prime-danger)]">*</span></label>
                    <select name="is_active" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3.5 text-base">
                        <option value="1" @selected((string) old('is_active', '1') === '1')>Aktif</option>
                        <option value="0" @selected((string) old('is_active') === '0')>Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-[var(--color-prime-border)] pt-4">
                <x-ui.button variant="secondary" data-modal-close="user-create-modal">Batal</x-ui.button>
                <x-ui.button type="submit">Simpan</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="user-edit-modal" title="Edit User">
        <form action="{{ $editingUserData ? route('settings-users.update', $editingUserData->id) : '#' }}" method="POST" class="space-y-5" data-user-edit-form>
            @csrf
            @method('PUT')
            <input type="hidden" name="modal_action" value="edit">
            <input type="hidden" name="user_id" value="{{ old('user_id', $editingUserData?->id) }}" data-user-edit-field="id">

            <div>
                <label class="mb-2 block text-base font-semibold text-[var(--color-prime-ink)]">Nama <span class="text-[var(--color-prime-danger)]">*</span></label>
                <input type="text" name="name" value="{{ old('name', $editingUserData?->name) }}" data-user-edit-field="name" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3.5 text-base" />
                @if (($modalState['action'] ?? null) === 'edit') @error('name')<p class="mt-2 text-sm text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror @endif
            </div>

            <div>
                <label class="mb-2 block text-base font-semibold text-[var(--color-prime-ink)]">Username <span class="text-[var(--color-prime-danger)]">*</span></label>
                <input type="text" name="username" value="{{ old('username', $editingUserData?->username) }}" data-user-edit-field="username" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3.5 text-base" />
                @if (($modalState['action'] ?? null) === 'edit') @error('username')<p class="mt-2 text-sm text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror @endif
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-base font-semibold text-[var(--color-prime-ink)]">Role <span class="text-[var(--color-prime-danger)]">*</span></label>
                    <select name="role" data-user-edit-field="role" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3.5 text-base">
                        <option value="admin" @selected(old('role', $editingUserData?->role) === 'admin')>Admin</option>
                        <option value="operator" @selected(old('role', $editingUserData?->role) === 'operator')>Operator</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-base font-semibold text-[var(--color-prime-ink)]">Status <span class="text-[var(--color-prime-danger)]">*</span></label>
                    <select name="is_active" data-user-edit-field="active" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3.5 text-base">
                        <option value="1" @selected((string) old('is_active', $editingUserData?->is_active ? '1' : '0') === '1')>Aktif</option>
                        <option value="0" @selected((string) old('is_active', $editingUserData?->is_active ? '1' : '0') === '0')>Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-[var(--color-prime-border)] pt-4">
                <x-ui.button variant="secondary" data-modal-close="user-edit-modal">Batal</x-ui.button>
                <x-ui.button type="submit">Simpan Perubahan</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="user-reset-modal" title="Reset Password">
        <form action="{{ $resetPasswordUserData ? route('settings-users.reset-password', $resetPasswordUserData->id) : '#' }}" method="POST" class="space-y-5" data-user-reset-form>
            @csrf
            @method('PATCH')
            <input type="hidden" name="modal_action" value="reset_password">
            <input type="hidden" name="user_id" value="{{ old('user_id', $resetPasswordUserData?->id) }}" data-user-reset-field="id">

            <p class="text-base text-[var(--color-prime-muted)]" data-user-reset-label>
                Ubah sandi untuk: {{ $resetPasswordUserData?->name ? $resetPasswordUserData->name . ' (@' . $resetPasswordUserData->username . ')' : '-' }}
            </p>

            <div>
                <label class="mb-2 block text-base font-semibold text-[var(--color-prime-ink)]">Password Baru <span class="text-[var(--color-prime-danger)]">*</span></label>
                <input type="password" name="new_password" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3.5 text-base" />
                @if (($modalState['action'] ?? null) === 'reset_password') @error('new_password')<p class="mt-2 text-sm text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror @endif
            </div>

            <div>
                <label class="mb-2 block text-base font-semibold text-[var(--color-prime-ink)]">Konfirmasi Password Baru <span class="text-[var(--color-prime-danger)]">*</span></label>
                <input type="password" name="new_password_confirmation" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3.5 text-base" />
            </div>

            <div class="flex justify-end gap-3 border-t border-[var(--color-prime-border)] pt-4">
                <x-ui.button variant="secondary" data-modal-close="user-reset-modal">Batal</x-ui.button>
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[var(--color-prime-warning)] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#dd6504]">
                    Reset Password
                </button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="user-status-modal">
        <div class="flex items-start gap-4">
            <div data-user-status-icon-wrap class="flex h-12 w-12 items-center justify-center rounded-full bg-[var(--color-prime-warning-soft)] text-[var(--color-prime-warning)]">
                <x-ui.icon data-user-status-icon name="power" class="h-6 w-6" />
            </div>
            <div class="flex-1">
                <h3 data-user-status-title class="text-2xl font-semibold tracking-[-0.03em] text-[var(--color-prime-ink)]">Nonaktifkan User</h3>
                <p data-user-status-description class="mt-3 text-lg leading-8 text-[var(--color-prime-muted)]">User tidak bisa login setelah dinonaktifkan.</p>
            </div>
        </div>

        <div class="mt-6 border-t border-[var(--color-prime-border)] pt-5">
            <form action="#" method="POST" class="flex justify-end gap-3" data-user-status-form>
                @csrf
                @method('PATCH')
                <x-ui.button variant="secondary" data-modal-close="user-status-modal">Batalkan</x-ui.button>
                <button type="submit" data-user-status-submit class="inline-flex items-center justify-center rounded-xl bg-[var(--color-prime-warning)] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#dd6504]">
                    Nonaktifkan
                </button>
            </form>
        </div>
    </x-ui.modal>

    <x-ui.modal id="user-delete-modal">
        <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[var(--color-prime-danger-soft)] text-[var(--color-prime-danger)]">
                <x-ui.icon name="trash" class="h-6 w-6" />
            </div>
            <div class="flex-1">
                <h3 class="text-2xl font-semibold tracking-[-0.03em] text-[var(--color-prime-ink)]">Delete User</h3>
                <p class="mt-3 text-lg leading-8 text-[var(--color-prime-muted)]" data-user-delete-description>
                    User akan dihapus dari sistem. History PM dan breakdown yang pernah dikerjakan user tetap tersimpan.
                </p>
            </div>
        </div>

        <div class="mt-6 border-t border-[var(--color-prime-border)] pt-5">
            <form action="#" method="POST" class="flex justify-end gap-3" data-user-delete-form>
                @csrf
                @method('DELETE')
                <x-ui.button variant="secondary" data-modal-close="user-delete-modal">Batalkan</x-ui.button>
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[var(--color-prime-danger)] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#cf2e2e]">
                    Delete
                </button>
            </form>
        </div>
    </x-ui.modal>
</x-layouts.app>

