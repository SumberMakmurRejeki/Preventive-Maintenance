@props([
    'actorName',
    'role',
])

@php
    $roleLabel = match ($role) {
        'admin' => 'Admin',
        'operator' => 'Operator',
        'guest' => 'Tamu',
        default => 'User',
    };
@endphp

<div class="relative" data-dropdown>
    <button type="button" data-dropdown-trigger class="inline-flex min-h-10 items-center gap-2.5 rounded-2xl border border-transparent bg-white px-1.5 py-1 text-left transition hover:border-[rgba(0,0,0,0.08)] hover:bg-[var(--color-prime-soft)]">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[rgba(0,0,0,0.08)] bg-[var(--color-prime-soft)] text-[var(--color-prime-ink)]">
            <x-ui.icon name="user-circle" class="h-5 w-5" />
        </span>
        <div class="hidden text-right sm:block">
            <p class="text-base font-semibold text-[var(--color-prime-ink)]">{{ $roleLabel }}</p>
        </div>
        <x-ui.icon name="chevron-down" class="h-4 w-4 text-[var(--color-prime-muted)]" />
    </button>

    <div class="absolute right-0 top-[calc(100%+0.6rem)] hidden min-w-64 rounded-2xl border border-[var(--color-prime-border)] bg-white p-3 shadow-[0_22px_44px_-30px_rgba(15,23,42,0.35)]" data-dropdown-menu>
        <div class="rounded-[1rem] bg-[var(--color-prime-soft)] p-4">
            <p class="text-sm font-semibold text-[var(--color-prime-ink)]">{{ $actorName }}</p>
            <p class="mt-1 text-xs uppercase tracking-[0.2em] text-[var(--color-prime-muted)]">{{ $roleLabel }}</p>
        </div>

        <form action="{{ route('logout') }}" method="POST" class="mt-3">
            @csrf
            <x-ui.button type="submit" variant="secondary" full>
                Logout
            </x-ui.button>
        </form>
    </div>
</div>
