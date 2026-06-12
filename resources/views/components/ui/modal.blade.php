@props([
    'id',
    'title' => null,
    'overlayClass' => 'bg-slate-950/20',
    'panelClass' => 'max-w-xl rounded-[var(--radius-modal)] bg-white p-6 shadow-[var(--shadow-level-3)]',
])

<div id="{{ $id }}" class="prime-modal fixed inset-0 z-50 hidden items-center justify-center px-4 {{ $overlayClass }}" aria-hidden="true">
    <div class="w-full {{ $panelClass }}">
        @if ($title)
            <div class="flex items-start justify-between gap-4">
                <h3 class="text-lg font-semibold text-[var(--color-prime-ink)]">{{ $title }}</h3>
                <button type="button" data-modal-close="{{ $id }}" class="rounded-[var(--radius-micro)] p-2 text-[var(--color-prime-muted)] transition hover:bg-[var(--color-prime-soft)] hover:text-[var(--color-prime-ink)]">
                    <x-ui.icon name="x-mark" class="h-5 w-5" />
                </button>
            </div>
        @endif

        <div class="{{ $title ? 'mt-5' : '' }}">
            {{ $slot }}
        </div>
    </div>
</div>
