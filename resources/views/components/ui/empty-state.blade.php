@props([
    'title' => 'Belum ada data',
    'message' => 'Konten akan muncul ketika data tersedia.',
])

<div {{ $attributes->merge(['class' => 'flex min-h-40 flex-col items-center justify-center rounded-[1.35rem] border border-dashed border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] px-6 py-10 text-center']) }}>
    <span class="rounded-2xl bg-white p-3 text-[var(--color-prime-primary)] shadow-sm">
        <x-ui.icon name="chart-bar" class="h-6 w-6" />
    </span>
    <h3 class="mt-4 text-base font-semibold text-[var(--color-prime-ink)]">{{ $title }}</h3>
    <p class="mt-2 max-w-sm text-sm leading-6 text-[var(--color-prime-muted)]">{{ $message }}</p>
</div>
