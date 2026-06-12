@props([
    'title' => null,
    'subtitle' => null,
    'padding' => 'p-6',
    'height' => null,
])

<section {{ $attributes->merge(['class' => trim("rounded-[var(--radius-card)] border border-[var(--color-prime-border)] bg-white shadow-none {$padding} {$height}")]) }}>
    @if ($title || $subtitle)
        <header class="mb-5 flex items-start justify-between gap-4">
            <div>
                @if ($title)
                    <h3 class="text-[15px] font-semibold text-[var(--color-prime-ink)]">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="mt-1 text-xs text-[var(--color-prime-muted)]">{{ $subtitle }}</p>
                @endif
            </div>

            @if (isset($actions))
                <div>{{ $actions }}</div>
            @endif
        </header>
    @endif

    {{ $slot }}
</section>
