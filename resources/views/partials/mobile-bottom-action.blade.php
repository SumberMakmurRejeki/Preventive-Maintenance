@props([
    'actions' => [],
])

@if ($actions !== [])
    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-[var(--color-prime-border)] bg-white/95 px-4 py-3 shadow-[0_-12px_40px_-32px_rgba(15,23,42,0.45)] backdrop-blur lg:hidden">
        <div class="grid gap-2" style="grid-template-columns: repeat({{ count($actions) }}, minmax(0, 1fr));">
            @foreach ($actions as $action)
                <x-ui.button
                    :variant="$action['variant'] ?? 'secondary'"
                    :disabled="$action['disabled'] ?? false"
                    full
                >
                    {{ $action['label'] }}
                </x-ui.button>
            @endforeach
        </div>
    </div>
@endif
