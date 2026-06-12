@props([
    'name' => 'search',
    'placeholder' => 'Cari data...',
])

<label class="relative block">
    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-[var(--color-prime-muted)]">
        <x-ui.icon name="chart-bar" class="h-4 w-4 opacity-60" />
    </span>
    <input
        type="search"
        name="{{ $name }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->merge(['class' => 'w-full rounded-[var(--radius-micro)] border border-[var(--color-prime-border)] bg-white py-3 pl-10 pr-4 text-base font-normal tracking-normal text-[var(--color-prime-ink)] outline-none transition placeholder:text-[var(--color-prime-placeholder)] focus:border-[var(--color-prime-primary)] focus:ring-4 focus:ring-[var(--color-prime-primary)]/10']) }}
    />
</label>
