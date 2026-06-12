<x-layouts.app title="Input Breakdown" heading="" :actor-name="$actorName" :role="$role" :show-operator-bottom-actions="false">
    <section class="mt-8 space-y-6 font-['NotionInter','Inter',ui-sans-serif,system-ui,sans-serif] text-[rgba(0,0,0,0.95)]" data-breakdown-machine-page>
        <div class="space-y-6">
            <div class="flex items-center gap-2 text-[15px] font-medium text-[rgba(0,0,0,0.95)]">
                <span>Breakdown Management</span>
                <span class="text-[#a39e98]">/</span>
                <span>Input Breakdown</span>
            </div>

            <div>
                <h2 class="text-[40px] font-bold leading-[1.1] tracking-[-0.8px] text-[rgba(0,0,0,0.95)]">Input Breakdown</h2>
                <p class="mt-2 text-[16px] font-normal leading-[1.5] text-[#615d59]">Pilih mesin yang mengalami kerusakan untuk mencatat breakdown OPEN.</p>
            </div>
        </div>

        <x-ui.card padding="p-0" class="table-card mx-auto w-full max-w-[1440px] overflow-hidden rounded-[12px] border border-[rgba(0,0,0,0.1)] bg-white shadow-[rgba(0,0,0,0.02)_0px_4px_18px]">
            <div class="table-toolbar border-b border-[rgba(0,0,0,0.1)] px-[14px] py-[14px] sm:px-4 sm:py-4">
                <div class="flex flex-col items-stretch gap-[10px] md:items-center md:gap-3 lg:flex-row lg:items-center lg:gap-3" data-breakdown-filter-bar>
                    <label class="search-control relative block min-w-0 lg:w-[320px]" aria-label="Cari mesin">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-[#615d59]">
                            <x-ui.icon name="magnifying-glass" class="h-[18px] w-[18px]" />
                        </span>
                        <input
                            type="search"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Cari kode atau nama mesin..."
                            class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white py-0 pl-11 pr-9 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition placeholder:text-[#615d59] focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10"
                            data-filter-search
                        >
                    </label>

                    <label class="filter-control block lg:w-40" aria-label="Filter lokasi">
                        <select name="location_id" class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10" data-filter-location>
                            <option value="0">Semua Lokasi</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected((int) $filters['location_id'] === $location->id)>{{ $location->location_name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="filter-control block lg:w-[190px]" aria-label="Filter status breakdown">
                        <select name="status_breakdown" class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10" data-filter-breakdown-status>
                            <option value="">Semua Status</option>
                            <option value="none" @selected($filters['status_breakdown'] === 'none')>Tidak Ada Breakdown</option>
                            <option value="open" @selected($filters['status_breakdown'] === 'open')>Ada Breakdown OPEN</option>
                        </select>
                    </label>

                    <div class="reset-filter flex items-center justify-end lg:ml-auto">
                        <button type="button" class="inline-flex h-10 w-full items-center justify-center rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-4 text-[14px] font-medium text-[rgba(0,0,0,0.95)] transition hover:bg-[rgba(0,0,0,0.03)] lg:w-auto" data-filter-reset>
                            Reset Filter
                        </button>
                    </div>
                </div>
            </div>

            @if ($loadError)
                <div class="px-6 py-8">
                    <x-ui.empty-state
                        title="Data mesin gagal dimuat"
                        message="{{ $loadErrorMessage }}"
                    />
                </div>
            @else
                @php
                    $desktopRows = collect($machines->items());
                    $hasData = $desktopRows->isNotEmpty();
                @endphp

                <div class="hidden overflow-x-auto lg:block" data-table-desktop-wrap>
                    <table class="min-w-full table-fixed" data-table-desktop>
                        <colgroup>
                            <col style="width:56px;">
                            <col style="width:140px;">
                            <col style="width:300px;">
                            <col style="width:160px;">
                            <col style="width:130px;">
                            <col style="width:160px;">
                            <col style="width:120px;">
                        </colgroup>
                        <thead class="border-b border-[rgba(0,0,0,0.1)] bg-[#f6f5f4]">
                            <tr>
                                <th class="px-4 py-3 text-left text-[13px] font-semibold text-[#615d59]">No</th>
                                <th class="px-4 py-3 text-left text-[13px] font-semibold text-[#615d59]">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-[rgba(0,0,0,0.95)]" data-sort-trigger data-sort-group="breakdown-input" data-sort-field="code" aria-label="Urutkan Kode Mesin">
                                        Kode Mesin
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="breakdown-input" data-sort-field="code">↕</span>
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left text-[13px] font-semibold text-[#615d59]">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-[rgba(0,0,0,0.95)]" data-sort-trigger data-sort-group="breakdown-input" data-sort-field="name" aria-label="Urutkan Nama Mesin">
                                        Nama Mesin
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="breakdown-input" data-sort-field="name">↕</span>
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left text-[13px] font-semibold text-[#615d59]">Lokasi</th>
                                <th class="px-4 py-3 text-left text-[13px] font-semibold text-[#615d59]">Status Mesin</th>
                                <th class="px-4 py-3 text-center text-[13px] font-semibold text-[#615d59]">Breakdown OPEN</th>
                                <th class="px-4 py-3 text-center text-[13px] font-semibold text-[#615d59]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white" data-table-desktop-body>
                            @foreach ($machines as $machine)
                                @php
                                    $isActive = (bool) $machine->is_active;
                                    $openCount = (int) ($machine->breakdown_open_count ?? 0);
                                @endphp
                                <tr
                                    class="h-14 border-b border-[rgba(0,0,0,0.08)] bg-white text-[14px] text-[rgba(0,0,0,0.95)] transition hover:bg-[#f6f5f4]"
                                    data-machine-row
                                    data-breakdown-machine-row
                                    data-code="{{ strtolower($machine->machine_code) }}"
                                    data-name="{{ strtolower($machine->machine_name) }}"
                                    data-location-id="{{ (int) $machine->location_id }}"
                                    data-open-count="{{ $openCount }}"
                                >
                                    <td class="px-4 py-3 text-sm text-[var(--color-prime-muted)]">{{ $machines->firstItem() + $loop->index }}</td>
                                    <td class="px-4 py-3 text-sm font-semibold text-[var(--color-prime-ink)]">{{ $machine->machine_code }}</td>
                                    <td class="px-4 py-3 text-sm text-[var(--color-prime-ink)] truncate" title="{{ $machine->machine_name }}">{{ $machine->machine_name }}</td>
                                    <td class="px-4 py-3 text-sm text-[var(--color-prime-muted)] truncate" title="{{ $machine->location?->location_name ?? '-' }}">{{ $machine->location?->location_name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex h-6 items-center rounded-full border px-2.5 text-xs font-semibold {{ $isActive ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-orange-200 bg-orange-50 text-orange-700' }}">
                                            {{ $isActive ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm">
                                        @if ($openCount > 0)
                                            <span class="inline-flex h-6 items-center rounded-full border border-[#fed7aa] bg-[#fff7ed] px-2.5 text-xs font-semibold text-[#9a3412]">{{ $openCount }} OPEN</span>
                                        @else
                                            <span class="inline-flex h-6 items-center rounded-full border border-black/10 bg-[#f8f7f5] px-2.5 text-xs font-semibold text-[#615d59]">Tidak ada</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if ($isActive)
                                            <a
                                                href="{{ route('machines.breakdown-input', $machine) }}"
                                                class="mx-auto inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[var(--color-prime-border)] bg-white text-[var(--color-prime-primary)] hover:bg-[var(--color-prime-soft)]"
                                                title="{{ $openCount > 0 ? 'Input Breakdown (mesin sudah punya breakdown OPEN)' : 'Input Breakdown' }}"
                                                aria-label="Input Breakdown"
                                            >
                                                <x-ui.icon name="wrench-screwdriver" class="h-4 w-4" />
                                            </a>
                                        @else
                                            <button
                                                type="button"
                                                class="mx-auto inline-flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-lg border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] text-[var(--color-prime-placeholder)] opacity-70"
                                                title="Mesin nonaktif tidak bisa input breakdown baru."
                                                aria-label="Mesin nonaktif tidak bisa input breakdown baru."
                                                disabled
                                            >
                                                <x-ui.icon name="wrench-screwdriver" class="h-4 w-4" />
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="space-y-2.5 p-4 lg:hidden" data-mobile-list>
                    @foreach ($machines as $machine)
                        @php
                            $isActive = (bool) $machine->is_active;
                            $openCount = (int) ($machine->breakdown_open_count ?? 0);
                        @endphp
                        <article
                            class="rounded-2xl border border-[var(--color-prime-border)] bg-white p-4"
                            data-machine-row
                            data-breakdown-machine-row
                            data-code="{{ strtolower($machine->machine_code) }}"
                            data-name="{{ strtolower($machine->machine_name) }}"
                            data-location-id="{{ (int) $machine->location_id }}"
                            data-open-count="{{ $openCount }}"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold tracking-[0.08em] text-[var(--color-prime-ink)]">{{ $machine->machine_code }}</p>
                                <span class="inline-flex h-6 items-center rounded-full border px-2.5 text-xs font-semibold {{ $isActive ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-orange-200 bg-orange-50 text-orange-700' }}">
                                    {{ $isActive ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                            <p class="mt-1 text-base font-semibold text-[var(--color-prime-ink)]">{{ $machine->machine_name }}</p>
                            <p class="mt-2 text-sm text-[var(--color-prime-muted)]">Lokasi: {{ $machine->location?->location_name ?? '-' }}</p>
                            <p class="mt-1 text-sm text-[var(--color-prime-muted)]">
                                Breakdown OPEN:
                                <span class="font-semibold {{ $openCount > 0 ? 'text-[#9a3412]' : 'text-[#615d59]' }}">{{ $openCount > 0 ? $openCount.' OPEN' : 'Tidak ada' }}</span>
                            </p>

                            @if ($isActive)
                                <a href="{{ route('machines.breakdown-input', $machine) }}" class="mt-3 inline-flex h-10 w-full items-center justify-center rounded-xl border border-[var(--color-prime-border)] bg-white px-4 text-sm font-semibold text-[var(--color-prime-primary)]">
                                    Input Breakdown
                                </a>
                            @else
                                <button type="button" class="mt-3 inline-flex h-10 w-full cursor-not-allowed items-center justify-center rounded-xl border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] px-4 text-sm font-semibold text-[var(--color-prime-placeholder)]" disabled>
                                    Input Breakdown
                                </button>
                            @endif
                        </article>
                    @endforeach
                </div>

                <div class="px-6 py-10 text-center {{ $hasData ? 'hidden' : '' }}" data-empty-initial>
                    <x-ui.empty-state
                        title="Belum ada data mesin."
                        message="Tambahkan mesin terlebih dahulu sebelum mencatat breakdown."
                    />
                </div>

                <div class="hidden px-6 py-10 text-center" data-empty-filter>
                    <x-ui.empty-state
                        title="Tidak ada mesin ditemukan."
                        message="Coba ubah kata kunci atau reset filter."
                    />
                    <div class="mt-4">
                        <button type="button" class="inline-flex h-10 items-center justify-center rounded-lg border border-[var(--color-prime-border)] bg-white px-4 text-sm font-semibold text-[var(--color-prime-ink)]" data-filter-reset>
                            Reset Filter
                        </button>
                    </div>
                </div>

                @if ($hasData)
                    <x-ui.numeric-pagination :paginator="$machines" data-pagination-wrap />
                @endif
            @endif
        </x-ui.card>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.querySelector('[data-filter-search]');
            const locationSelect = document.querySelector('[data-filter-location]');
            const breakdownStatusSelect = document.querySelector('[data-filter-breakdown-status]');
            const resetButtons = document.querySelectorAll('[data-filter-reset]');
            const rows = Array.from(document.querySelectorAll('[data-machine-row]'));
            const desktopWrap = document.querySelector('[data-table-desktop-wrap]');
            const mobileWrap = document.querySelector('[data-mobile-list]');
            const emptyInitial = document.querySelector('[data-empty-initial]');
            const emptyFilter = document.querySelector('[data-empty-filter]');
            const paginationWrap = document.querySelector('[data-pagination-wrap]');

            if (!searchInput || !locationSelect || !breakdownStatusSelect || rows.length === 0) {
                return;
            }

            const applyFilters = () => {
                const search = searchInput.value.trim().toLowerCase();
                const location = locationSelect.value;
                const breakdownStatus = breakdownStatusSelect.value;

                let visibleCount = 0;

                rows.forEach((row) => {
                    const code = row.dataset.code || '';
                    const name = row.dataset.name || '';
                    const locationId = row.dataset.locationId || '';
                    const openCount = Number(row.dataset.openCount || 0);

                    const matchSearch = search === '' || code.includes(search) || name.includes(search);
                    const matchLocation = location === '0' || locationId === location;
                    const matchBreakdown = breakdownStatus === ''
                        || (breakdownStatus === 'none' && openCount === 0)
                        || (breakdownStatus === 'open' && openCount > 0);

                    const visible = matchSearch && matchLocation && matchBreakdown;
                    row.classList.toggle('hidden', !visible);

                    if (visible) {
                        visibleCount += 1;
                    }
                });

                const hasFilter = search !== '' || location !== '0' || breakdownStatus !== '';

                if (emptyFilter) {
                    emptyFilter.classList.toggle('hidden', !(hasFilter && visibleCount === 0));
                }

                if (emptyInitial) {
                    emptyInitial.classList.add('hidden');
                }

                if (desktopWrap) {
                    desktopWrap.classList.toggle('hidden', visibleCount === 0);
                }

                if (mobileWrap) {
                    mobileWrap.classList.toggle('hidden', visibleCount === 0);
                }

                if (paginationWrap) {
                    paginationWrap.classList.toggle('hidden', hasFilter);
                }
            };

            let debounceTimer;
            searchInput.addEventListener('input', () => {
                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(applyFilters, 300);
            });

            locationSelect.addEventListener('change', applyFilters);
            breakdownStatusSelect.addEventListener('change', applyFilters);

            resetButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    searchInput.value = '';
                    locationSelect.value = '0';
                    breakdownStatusSelect.value = '';
                    applyFilters();
                    searchInput.focus();
                });
            });

            applyFilters();
        });
    </script>
</x-layouts.app>
