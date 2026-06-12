@props([
    'role',
])

@php
    $menuGroups = $role === 'guest'
        ? [
            [
                'label' => 'Monitoring',
                'open' => true,
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'dashboard', 'routePatterns' => ['dashboard']],
                ],
            ],
        ]
        : [
            [
                'label' => 'Monitoring',
                'open' => true,
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'dashboard', 'routePatterns' => ['dashboard']],
                ],
            ],
            [
                'label' => 'PM Management',
                'icon' => 'shield-check',
                'open' => request()->routeIs('machines.show') || request()->routeIs('master-lokasi.*') || request()->routeIs('master-mesin.*') || request()->routeIs('master-checksheet.*') || request()->routeIs('pm-review.*'),
                'items' => [
                    ['label' => 'Master Mesin', 'icon' => 'server', 'route' => 'master-mesin.index', 'routePatterns' => ['master-mesin.*']],
                    ['label' => 'Master Lokasi', 'icon' => 'map-pin', 'route' => 'master-lokasi.index', 'routePatterns' => ['master-lokasi.*']],
                    ['label' => 'Master PM Checksheet', 'icon' => 'clipboard-check', 'route' => 'master-checksheet.index', 'routePatterns' => ['master-checksheet.*']],
                    ['label' => 'PM Review', 'icon' => 'file-search', 'route' => 'pm-review.index', 'routePatterns' => ['pm-review.*']],
                ],
            ],
            [
                'label' => 'Breakdown Management',
                'icon' => 'exclamation-triangle',
                'open' => request()->routeIs('breakdown-input.*') || request()->routeIs('breakdown-review.*'),
                'items' => [
                    ['label' => 'Input Breakdown', 'icon' => 'exclamation-triangle', 'route' => 'breakdown-input.index', 'routePatterns' => ['breakdown-input.*', 'machines.breakdown-input']],
                    ['label' => 'Review Breakdown', 'icon' => 'tools-crossed', 'route' => 'breakdown-review.index', 'routePatterns' => ['breakdown-review.*']],
                ],
            ],
            [
                'label' => 'Kalender',
                'open' => false,
                'items' => [
                    ['label' => 'Kalender', 'icon' => 'calendar', 'route' => 'calendar.index', 'routePatterns' => ['calendar.*']],
                ],
            ],
            [
                'label' => 'Report',
                'icon' => 'chart-bar',
                'open' => request()->routeIs('report-pm.*') || request()->routeIs('report-breakdown.*') || request()->routeIs('report-exports.*'),
                'items' => [
                    ['label' => 'Report PM', 'icon' => 'chart-bar', 'route' => 'report-pm.index', 'routePatterns' => ['report-pm.*', 'report-exports.*']],
                    ['label' => 'Report Breakdown', 'icon' => 'chart-bar', 'route' => 'report-breakdown.index', 'routePatterns' => ['report-breakdown.*']],
                ],
            ],
            ...($role === 'admin'
                ? [[
                    'label' => 'Pengaturan User',
                    'open' => false,
                    'items' => [
                        ['label' => 'Pengaturan User', 'icon' => 'user-circle', 'route' => 'settings-users.index', 'routePatterns' => ['settings-users.*']],
                    ],
                ]]
                : []),
        ];
@endphp

<aside id="prime-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-[18.5rem] -translate-x-full flex-col border-r border-[var(--color-prime-sidebar-border)] bg-[#061A33] px-4 py-6 text-white transition-all duration-300 lg:translate-x-0 select-none">
    <div class="flex items-start justify-between gap-3 px-3">
        <a href="{{ route('dashboard') }}" class="block min-w-0 flex-1">
            <img
                src="{{ asset('images/prime-logo.png') }}"
                alt="PRIME logo"
                data-sidebar-label
                class="h-auto w-full max-w-[11.5rem]"
            >
            <p data-sidebar-label class="mt-3 text-[12px] font-medium uppercase tracking-[0.12em] text-white/80">Maintenance System</p>
        </a>

        <a href="{{ route('dashboard') }}" class="prime-sidebar-logo-compact hidden lg:hidden" aria-label="PRIME Dashboard">
            <img
                src="{{ asset('images/prime-logo.png') }}"
                alt="PRIME logo"
                class="h-auto w-10"
            >
        </a>

        <button
            type="button"
            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-white/80 transition hover:bg-white/10 hover:text-white lg:hidden"
            data-sidebar-toggle
            aria-label="Tutup sidebar"
        >
            <x-ui.icon name="x-mark" class="h-5 w-5 [stroke-width:1.9]" />
        </button>
    </div>

    <nav class="mt-8 flex-1 space-y-2 overflow-y-auto pr-1">
        @foreach ($menuGroups as $group)
            @php
                $hasSingleItem = count($group['items']) === 1 && ! isset($group['icon']);
            @endphp

            @if ($hasSingleItem)
                @php
                    $item = $group['items'][0];
                    $active = collect($item['routePatterns'] ?? [])->contains(fn (string $pattern): bool => request()->routeIs($pattern));
                @endphp

                <a
                    href="{{ $item['route'] ? route($item['route']) : '#' }}"
                    @class([
                        'flex h-[46px] items-center gap-3 rounded-[12px] px-3 py-0 text-[15px] tracking-normal transition',
                        'bg-[#0075DE] font-semibold text-white shadow-[inset_0_0_0_1px_rgba(255,255,255,0.08)]' => $active,
                        'font-medium text-white/86 hover:bg-white/6 hover:text-white' => ! $active,
                    ])
                >
                    <span @class([
                        'inline-flex h-8 w-8 items-center justify-center rounded-[10px]',
                        'bg-white/12 text-white' => $active,
                        'bg-transparent text-white/72' => ! $active,
                    ])>
                        <x-ui.icon :name="$item['icon']" class="h-[20px] w-[20px] [stroke-width:1.6]" />
                    </span>
                    <span data-sidebar-label>{{ $item['label'] }}</span>
                </a>
            @else
                <details class="group select-none" @if($group['open']) open @endif>
                    <summary @class([
                        'flex cursor-pointer list-none items-center justify-between px-3 pb-2 text-[12px] font-semibold uppercase tracking-[0.08em] text-white/55',
                        'mt-5 border-t border-[var(--color-prime-sidebar-border)] pt-5' => ! $loop->first,
                        'pt-1' => $loop->first,
                    ])>
                        <span class="inline-flex items-center gap-2">
                            @if (isset($group['icon']))
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-[8px] bg-transparent text-white/55">
                                    <x-ui.icon :name="$group['icon']" class="h-[18px] w-[18px] [stroke-width:1.5]" />
                                </span>
                            @endif
                            <span data-sidebar-label>{{ $group['label'] }}</span>
                        </span>
                        <x-ui.icon data-sidebar-label name="chevron-down" class="h-4 w-4 [stroke-width:1.5] text-white/45 transition group-open:rotate-180" />
                    </summary>

                    <div data-sidebar-label class="mt-1 space-y-1 px-1">
                        @foreach ($group['items'] as $item)
                            @php
                                $routePatterns = $item['routePatterns'] ?? [];
                                $active = collect($routePatterns)->contains(fn (string $pattern): bool => request()->routeIs($pattern));
                            @endphp

                            @if ($item['route'])
                                <a
                                    href="{{ route($item['route']) }}"
                                    @class([
                                        'flex h-[44px] items-center gap-3 rounded-[12px] px-3 py-0 text-[15px] tracking-normal transition',
                                        'bg-[#0075DE] font-semibold text-white shadow-[inset_0_0_0_1px_rgba(255,255,255,0.08)]' => $active,
                                        'font-medium text-white/86 hover:bg-white/6 hover:text-white' => ! $active,
                                    ])
                                >
                                    <span @class([
                                        'inline-flex h-8 w-8 items-center justify-center rounded-[10px]',
                                        'bg-white/12 text-white' => $active,
                                        'bg-transparent text-white/72' => ! $active,
                                    ])>
                                        <x-ui.icon :name="$item['icon']" class="h-[20px] w-[20px] [stroke-width:1.6]" />
                                    </span>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @else
                                <div class="flex h-[44px] items-center justify-between gap-3 rounded-[12px] px-3 py-0 text-[15px] font-medium text-white/86">
                                    <span class="inline-flex items-center gap-3">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-[8px] bg-transparent text-white/72">
                                            <x-ui.icon :name="$item['icon']" class="h-[18px] w-[18px] [stroke-width:1.5]" />
                                        </span>
                                        {{ $item['label'] }}
                                    </span>
                                    <span class="inline-flex h-[22px] items-center rounded-full bg-white/10 px-2.5 text-[12px] font-bold text-white">{{ $item['placeholder'] ?? 'Soon' }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </details>
            @endif
        @endforeach
    </nav>
</aside>
