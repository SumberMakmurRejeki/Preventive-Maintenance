@props([
    'label' => 'Aksi',
])

<div class="relative" data-dropdown>
    <button type="button" data-dropdown-trigger class="inline-flex items-center gap-2 rounded-xl border border-[var(--color-prime-border)] bg-white px-3.5 py-2.5 text-sm font-semibold text-[var(--color-prime-ink)] transition hover:border-[var(--color-prime-primary)]/35 hover:bg-[var(--color-prime-soft)]">
        {{ $label }}
        <x-ui.icon name="chevron-down" class="h-4 w-4" />
    </button>

    <div class="absolute right-0 top-[calc(100%+0.5rem)] hidden min-w-48 rounded-2xl border border-[var(--color-prime-border)] bg-white p-2 shadow-xl" data-dropdown-menu>
        {{ $slot }}
    </div>
</div>
