<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 border-t border-[var(--color-prime-border)] px-4 py-4 text-sm font-normal text-[var(--color-prime-muted)] md:flex-row md:items-center md:justify-between']) }}>
    {{ $slot }}
</div>
