@props([
    'variant' => 'info',
    'title' => null,
])

@php
    $classes = match ($variant) {
        'success' => 'border-[var(--color-prime-success)]/20 bg-[var(--color-prime-success-soft)] text-[var(--color-prime-success)]',
        'warning' => 'border-[var(--color-prime-warning)]/25 bg-[var(--color-prime-warning-soft)] text-[var(--color-prime-warning)]',
        'error' => 'border-[var(--color-prime-danger)]/20 bg-[var(--color-prime-danger-soft)] text-[var(--color-prime-danger)]',
        default => 'border-[var(--color-prime-primary)]/20 bg-[var(--color-prime-primary-soft)] text-[var(--color-prime-primary)]',
    };
@endphp

<div {{ $attributes->merge(['class' => "rounded-2xl border px-4 py-3 {$classes}"]) }}>
    @if ($title)
        <p class="text-sm font-semibold">{{ $title }}</p>
    @endif
    <div class="text-sm {{ $title ? 'mt-1' : '' }}">
        {{ $slot }}
    </div>
</div>
