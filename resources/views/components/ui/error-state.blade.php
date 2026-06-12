@props([
    'title' => 'Terjadi kendala',
    'message' => 'Silakan coba lagi atau cek filter yang dipakai.',
])

<div {{ $attributes->merge(['class' => 'rounded-[1.35rem] border border-[var(--color-prime-danger)]/15 bg-[var(--color-prime-danger-soft)] px-6 py-5']) }}>
    <p class="text-sm font-semibold text-[var(--color-prime-danger)]">{{ $title }}</p>
    <p class="mt-2 text-sm leading-6 text-[var(--color-prime-danger)]/85">{{ $message }}</p>
</div>
