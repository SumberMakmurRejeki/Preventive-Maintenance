@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'flex items-center justify-between gap-4']) }}>
    <div>
        <h2 class="text-sm font-semibold text-[var(--color-prime-ink)]">{{ $title }}</h2>
        @if ($subtitle)
            <p class="mt-1 text-xs text-[var(--color-prime-muted)]">{{ $subtitle }}</p>
        @endif
    </div>

    @if (isset($actions))
        <div>{{ $actions }}</div>
    @endif
</div>
