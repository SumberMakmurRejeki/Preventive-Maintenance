<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-[var(--radius-card)] border border-[var(--color-prime-border)] bg-white']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-[var(--color-prime-border)] text-left">
            {{ $slot }}
        </table>
    </div>
</div>
