@props([
    'variant' => 'neutral',
])

@php
    $classes = match ($variant) {
        'info' => 'bg-[var(--color-prime-active-badge-bg)] text-[var(--color-prime-active-badge-text)]',
        'success' => 'bg-[var(--color-prime-success-soft)] text-[var(--color-prime-success)]',
        'danger' => 'bg-[var(--color-prime-danger-soft)] text-[var(--color-prime-danger)]',
        'warning' => 'bg-[var(--color-prime-warning-soft)] text-[var(--color-prime-warning)]',
        'primary' => 'bg-[var(--color-prime-primary-soft)] text-[var(--color-prime-primary)]',
        default => 'bg-[var(--color-prime-soft)] text-[var(--color-prime-muted)]',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-[var(--radius-pill)] px-2.5 py-1 text-[12px] font-semibold tracking-[var(--tracking-micro)] {$classes}"]) }}>
    {{ $slot }}
</span>
