@props([
    'heading' => 'Dashboard Monitoring',
    'subheading' => null,
    'actorName',
    'role',
    'isOperatorShell' => false,
])

<header class="sticky top-0 z-30 border-b border-[rgba(0,0,0,0.08)] bg-white">
    <div class="flex min-h-16 items-center justify-between gap-4 px-4 py-2.5 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-3">
            @if (! $isOperatorShell)
                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[rgba(0,0,0,0.08)] bg-white text-[var(--color-prime-ink)] transition hover:border-[rgba(0,0,0,0.12)] hover:bg-[var(--color-prime-soft)]" data-sidebar-toggle data-sidebar-collapse-toggle aria-label="Toggle Sidebar">
                    <x-ui.icon name="bars-3" class="h-5 w-5" />
                </button>
            @endif

            @if (filled($heading) || filled($subheading))
                <div class="min-w-0">
                    @if (filled($heading))
                        <h1 class="truncate text-[18px] font-semibold tracking-[-0.03em] text-[var(--color-prime-ink)]">{{ $heading }}</h1>
                    @endif

                    @if (filled($subheading))
                        <p class="mt-0.5 truncate text-sm text-[var(--color-prime-muted)]">{{ $subheading }}</p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
            @if ($role === 'admin')
                @include('partials.notification-bell')
            @endif

            <div class="hidden h-8 w-px bg-[rgba(0,0,0,0.08)] sm:block"></div>

            @include('partials.user-dropdown', ['actorName' => $actorName, 'role' => $role])
        </div>
    </div>
</header>
