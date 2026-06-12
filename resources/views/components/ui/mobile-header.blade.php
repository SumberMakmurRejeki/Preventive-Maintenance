@props([
    'title',
    'backHref' => null,
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-3 lg:hidden']) }}>
    @if ($backHref)
        <a href="{{ $backHref }}" class="rounded-xl border border-[var(--color-prime-border)] bg-white p-2 text-[var(--color-prime-ink)]">
            <x-ui.icon name="arrow-right" class="h-5 w-5 rotate-180" />
        </a>
    @endif

    <h1 class="text-base font-semibold text-[var(--color-prime-ink)]">{{ $title }}</h1>
</div>
