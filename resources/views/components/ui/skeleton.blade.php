@props([
    'lines' => 3,
])

<div {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @for ($index = 0; $index < $lines; $index++)
        <div class="h-4 animate-pulse rounded-full bg-[var(--color-prime-soft)] {{ $index === 0 ? 'w-full' : ($index + 1 === $lines ? 'w-3/5' : 'w-5/6') }}"></div>
    @endfor
</div>
