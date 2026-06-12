@props([
    'label',
    'name',
    'checked' => false,
])

<label class="inline-flex items-center gap-3 text-sm text-[var(--color-prime-ink)]">
    <input
        type="checkbox"
        name="{{ $name }}"
        value="1"
        @checked(old($name, $checked))
        {{ $attributes->merge(['class' => 'h-4 w-4 rounded border-[var(--color-prime-border)] text-[var(--color-prime-primary)] focus:ring-[var(--color-prime-primary)]/20']) }}
    />
    <span>{{ $label }}</span>
</label>
