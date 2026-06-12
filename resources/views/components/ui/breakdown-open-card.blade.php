@props([
    'title' => 'Belum ada breakdown OPEN',
    'message' => 'Status breakdown aktif akan muncul di area ini.',
])

<x-ui.card {{ $attributes }} :title="$title">
    <p class="text-sm leading-6 text-[var(--color-prime-muted)]">{{ $message }}</p>
</x-ui.card>
