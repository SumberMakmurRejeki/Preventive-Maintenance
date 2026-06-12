@props([
    'label' => null,
    'type' => 'text',
    'name',
    'value' => null,
])

<label class="block space-y-2">
    @if ($label)
        <span class="text-xs font-semibold tracking-[var(--tracking-micro)] text-[var(--color-prime-muted)]">{{ $label }}</span>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        value="{{ old($name, $value) }}"
        {{ $attributes->merge(['class' => 'w-full rounded-[var(--radius-micro)] border border-[var(--color-prime-border)] bg-white px-3.5 py-3 text-base font-normal tracking-normal text-[var(--color-prime-ink)] outline-none transition placeholder:text-[var(--color-prime-placeholder)] focus:border-[var(--color-prime-primary)] focus:ring-4 focus:ring-[var(--color-prime-primary)]/10']) }}
    />
</label>
