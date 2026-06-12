@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-4 md:flex-row md:items-start md:justify-between']) }}>
    <div>
        <h1 class="text-2xl font-semibold tracking-[-0.03em] text-[var(--color-prime-ink)]">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1.5 text-sm text-[var(--color-prime-muted)]">{{ $subtitle }}</p>
        @endif
    </div>

    @if (isset($actions))
        <div>{{ $actions }}</div>
    @endif
</div>
