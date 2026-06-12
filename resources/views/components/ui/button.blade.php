@props([
    'variant' => 'primary',
    'size' => 'md',
    'full' => false,
    'type' => 'button',
])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-2 rounded-[var(--radius-micro)] border text-sm font-semibold tracking-normal transition duration-200 focus:outline-none focus:ring-2 focus:ring-[var(--color-prime-primary)]/20 focus:ring-offset-0 disabled:cursor-not-allowed disabled:opacity-50';

    $sizeClasses = match ($size) {
        'sm' => 'px-3 py-2 text-xs',
        'lg' => 'px-5 py-3.5 text-sm',
        default => 'px-4 py-3 text-sm',
    };

    $variantClasses = match ($variant) {
        'secondary' => 'border-[var(--color-prime-border)] bg-white text-[var(--color-prime-ink)] hover:border-[var(--color-prime-primary)]/40 hover:bg-[var(--color-prime-soft)]',
        'ghost' => 'border-transparent bg-transparent text-[var(--color-prime-muted)] hover:bg-[var(--color-prime-soft)] hover:text-[var(--color-prime-ink)]',
        'danger' => 'border-transparent bg-[var(--color-prime-danger)] text-white hover:bg-[var(--color-prime-danger-strong)]',
        'disabled' => 'border-[var(--color-prime-border)] bg-white text-[var(--color-prime-muted)]',
        default => 'border-transparent bg-[var(--color-prime-primary)] text-white hover:bg-[var(--color-prime-primary-strong)]',
    };

    $widthClass = $full ? 'w-full' : '';
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => trim("{$baseClasses} {$sizeClasses} {$variantClasses} {$widthClass}")]) }}>
    {{ $slot }}
</button>
