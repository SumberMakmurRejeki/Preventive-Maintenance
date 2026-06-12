@props([
    'items' => [],
])

<nav {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2 text-[15px] font-medium tracking-normal text-[var(--color-prime-ink)]']) }} aria-label="Breadcrumb">
    @foreach ($items as $item)
        @if (! $loop->first)
            <span class="text-[var(--color-prime-placeholder)]">/</span>
        @endif

        @if (! empty($item['href']))
            <a href="{{ $item['href'] }}" class="transition hover:text-[var(--color-prime-primary)]">{{ $item['label'] }}</a>
        @else
            <span>{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
