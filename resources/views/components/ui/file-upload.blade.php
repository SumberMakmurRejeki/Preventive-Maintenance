@props([
    'label' => 'Upload file',
    'name',
])

<label class="block">
    <span class="mb-2 block text-xs font-semibold text-[var(--color-prime-muted)]">{{ $label }}</span>
    <input
        type="file"
        name="{{ $name }}"
        {{ $attributes->merge(['class' => 'block w-full rounded-[1.1rem] border border-dashed border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] px-4 py-4 text-sm text-[var(--color-prime-muted)] file:mr-4 file:rounded-xl file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-prime-primary)]']) }}
    />
</label>
