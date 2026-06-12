@props([
    'message' => 'Aksi ini tidak tersedia dalam kondisi saat ini.',
])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] px-4 py-3 text-sm text-[var(--color-prime-muted)]']) }}>
    {{ $message }}
</div>
