<x-layouts.app
    title="Review Breakdown"
    heading=""
    :actor-name="$actorName"
    :role="$role"
    :show-operator-bottom-actions="false"
>
    <section class="mt-8 space-y-6 font-['NotionInter','Inter',ui-sans-serif,system-ui,sans-serif] text-[rgba(0,0,0,0.95)]" data-breakdown-review-page>
        <div class="space-y-6">
            <div class="flex items-center gap-2 text-[15px] font-medium text-[rgba(0,0,0,0.95)]">
                <span>Breakdown Management</span>
                <span class="text-[#a39e98]">/</span>
                <span>Review Breakdown</span>
            </div>

            <div>
                <h2 class="text-[40px] font-bold leading-[1.1] tracking-[-0.8px] text-[rgba(0,0,0,0.95)]">Review Breakdown</h2>
                <p class="mt-2 text-[16px] font-normal leading-[1.5] text-[#615d59]">Pantau riwayat kerusakan mesin, evaluasi perbaikan, dan kelola data breakdown.</p>
            </div>
        </div>

        <x-ui.card padding="p-0" class="table-card mx-auto w-full max-w-[1440px] overflow-hidden rounded-[12px] border border-[rgba(0,0,0,0.1)] bg-white shadow-[rgba(0,0,0,0.02)_0px_4px_18px]">
            <div class="table-toolbar border-b border-[rgba(0,0,0,0.1)] px-4 py-4">
                <form class="flex flex-col items-stretch gap-[10px] md:flex-wrap md:flex-row md:items-center md:gap-3" data-breakdown-review-filter-form>
                    <label class="relative block min-w-0 md:w-[400px]">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-[#615d59]">
                            <x-ui.icon name="magnifying-glass" class="h-[18px] w-[18px]" />
                        </span>
                        <input
                            type="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Cari kode breakdown, mesin, part, atau problem..."
                            class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white py-0 pl-11 pr-9 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition placeholder:text-[#615d59] focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10"
                            data-breakdown-review-filter-search
                        >
                        <span class="pointer-events-none absolute inset-y-0 right-3 hidden items-center text-[#615d59]" data-breakdown-review-filter-search-loading>
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-[#0075de]/30 border-t-[#0075de]"></span>
                        </span>
                    </label>

                    <select
                        class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 md:w-[160px]"
                        data-breakdown-review-filter-status
                    >
                        <option value="">Semua Status</option>
                        <option value="open" @selected($filters['status'] === 'open')>OPEN</option>
                        <option value="closed" @selected($filters['status'] === 'closed')>CLOSED</option>
                    </select>

                    <select
                        class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 md:w-[180px]"
                        data-breakdown-review-filter-location
                    >
                        <option value="">Semua Lokasi</option>
                        @foreach ($locations as $location)
                            <option value="{{ strtolower($location->location_name) }}" @selected((int) $filters['location_id'] === $location->id)>{{ $location->location_name }}</option>
                        @endforeach
                    </select>

                    <input
                        type="date"
                        value="{{ $filters['date'] }}"
                        class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 md:w-[160px]"
                        data-breakdown-review-filter-start-date
                        aria-label="Dari Tanggal"
                    >

                    <input
                        type="date"
                        value=""
                        class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 md:w-[160px]"
                        data-breakdown-review-filter-end-date
                        aria-label="Sampai Tanggal"
                    >

                    <button
                        type="button"
                        class="ml-0 inline-flex h-10 items-center justify-center rounded-[6px] border border-[rgba(0,0,0,0.1)] px-4 text-[14px] font-medium text-[rgba(0,0,0,0.95)] transition hover:bg-[rgba(0,0,0,0.03)] md:ml-auto"
                        data-breakdown-review-filter-reset
                    >
                        Reset Filter
                    </button>
                </form>
            </div>

            <div class="hidden px-6 py-10" data-breakdown-review-empty-filter>
                <x-ui.empty-state title="Tidak ada breakdown ditemukan." message="Coba ubah kata kunci atau reset filter." />
                <div class="mt-4 flex justify-center">
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center rounded-[6px] border border-[rgba(0,0,0,0.1)] px-4 text-[14px] font-medium text-[rgba(0,0,0,0.95)] transition hover:bg-[rgba(0,0,0,0.03)]"
                        data-breakdown-review-filter-empty-reset
                    >
                        Reset Filter
                    </button>
                </div>
            </div>

            @if ($breakdowns->count() === 0)
                <div class="px-6 py-10">
                    <x-ui.empty-state title="Belum ada data breakdown." message="Data breakdown OPEN dan CLOSED akan tampil di sini." />
                </div>
            @else
                <div class="hidden overflow-x-auto lg:block" data-breakdown-review-table-desktop>
                    <table class="machine-table min-w-full table-fixed border-separate border-spacing-0 [&_th]:h-12 [&_th]:whitespace-nowrap [&_th]:px-4 [&_td]:h-14 [&_td]:px-4 [&_td]:align-middle">
                        <colgroup>
                            <col style="width:56px">
                            <col style="width:180px">
                            <col style="width:170px">
                            <col style="width:220px">
                            <col style="width:180px">
                            <col style="width:220px">
                            <col style="width:120px">
                            <col style="width:160px">
                            <col style="width:96px">
                        </colgroup>
                        <thead class="bg-[#f6f5f4]">
                            <tr>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">No</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-[rgba(0,0,0,0.95)]" data-sort-trigger data-sort-group="breakdown-review" data-sort-field="code" aria-label="Urutkan Kode Breakdown">
                                        Kode Breakdown
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="breakdown-review" data-sort-field="code">↕</span>
                                    </button>
                                </th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-[rgba(0,0,0,0.95)]" data-sort-trigger data-sort-group="breakdown-review" data-sort-field="breakdownDate" aria-label="Urutkan Tanggal Breakdown">
                                        Tanggal
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="breakdown-review" data-sort-field="breakdownDate">↕</span>
                                    </button>
                                </th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-[rgba(0,0,0,0.95)]" data-sort-trigger data-sort-group="breakdown-review" data-sort-field="machine" aria-label="Urutkan Mesin">
                                        Mesin
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="breakdown-review" data-sort-field="machine">↕</span>
                                    </button>
                                </th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Part</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Lokasi</th>
                                <th class="text-center text-[13px] font-semibold text-[#615d59]">Status</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Downtime</th>
                                <th class="text-center text-[13px] font-semibold text-[#615d59]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            @foreach ($breakdowns as $breakdown)
                                @php
                                    $runningMinutes = $breakdown->status === 'open' ? (int) $breakdown->breakdown_at?->diffInMinutes(now()) : null;
                                    $downtimeMinutes = $breakdown->status === 'closed' ? (int) ($breakdown->downtime_minutes ?? 0) : (int) $runningMinutes;
                                    $downtimeHours = intdiv($downtimeMinutes, 60);
                                    $downtimeRemainderMinutes = $downtimeMinutes % 60;
                                    $downtimeLabel = $downtimeHours.' Jam '.$downtimeRemainderMinutes.' Menit';
                                    $partName = $breakdown->part_name_snapshot ?: ($breakdown->custom_part_name ?: '-');
                                    $breakdownDateRaw = optional($breakdown->breakdown_at)->format('Y-m-d') ?? '';
                                    $breakdownDateValue = optional($breakdown->breakdown_at)->format('Y-m-d H:i:s') ?? '';
                                    $isHighDowntime = $downtimeMinutes >= 1440;
                                @endphp
                                <tr
                                    class="border-b border-[rgba(0,0,0,0.08)] text-[14px] text-[rgba(0,0,0,0.95)] transition hover:bg-[#f6f5f4]"
                                    data-breakdown-review-row
                                    data-code="{{ strtolower($breakdown->breakdown_code) }}"
                                    data-machine="{{ strtolower($breakdown->machine_name_snapshot) }}"
                                    data-name="{{ strtolower($breakdown->machine_name_snapshot) }}"
                                    data-part="{{ strtolower($partName) }}"
                                    data-problem="{{ strtolower($breakdown->problem) }}"
                                    data-status="{{ strtolower($breakdown->status) }}"
                                    data-location="{{ strtolower($breakdown->location_name_snapshot) }}"
                                    data-breakdown-date="{{ $breakdownDateValue }}"
                                    data-breakdown-day="{{ $breakdownDateRaw }}"
                                >
                                    <td class="text-left">{{ $breakdowns->firstItem() + $loop->index }}</td>
                                    <td class="font-semibold">{{ $breakdown->breakdown_code }}</td>
                                    <td>{{ optional($breakdown->breakdown_at)->translatedFormat('d M Y, H:i') ?? '-' }}</td>
                                    <td class="max-w-[220px] overflow-hidden text-ellipsis whitespace-nowrap" title="{{ $breakdown->machine_name_snapshot }}">{{ $breakdown->machine_name_snapshot }}</td>
                                    <td class="max-w-[220px] whitespace-normal break-words leading-[1.4]" title="{{ $partName }}">{{ $partName }}</td>
                                    <td class="max-w-[180px] overflow-hidden text-ellipsis whitespace-nowrap" title="{{ $breakdown->location_name_snapshot }}">{{ $breakdown->location_name_snapshot }}</td>
                                    <td class="text-center">
                                        <span class="inline-flex h-[25px] items-center whitespace-nowrap rounded-[9999px] border px-[11px] text-[12px] font-semibold leading-[1.2] {{ $breakdown->status === 'open' ? 'border-[#fed7aa] bg-[#fff7ed] text-[#9a3412]' : 'border-[#abefc6] bg-[#ecfdf3] text-[#067647]' }}">
                                            {{ strtoupper($breakdown->status) }}
                                        </span>
                                    </td>
                                    <td class="font-semibold {{ $isHighDowntime ? 'text-[#a15c00]' : 'text-[rgba(0,0,0,0.95)]' }}">{{ $downtimeLabel }}</td>
                                    <td class="text-center">
                                        <a
                                            href="{{ route('breakdown-review.show', $breakdown->id) }}"
                                            class="prime-icon-tooltip relative inline-flex h-9 w-9 items-center justify-center rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)] hover:text-[#0075de]"
                                            data-tooltip="Detail Breakdown"
                                            aria-label="Detail Breakdown"
                                        >
                                            <x-ui.icon name="eye" class="h-[18px] w-[18px]" />
                                            <span class="sr-only">Detail Breakdown</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-[10px] p-3 lg:hidden" data-breakdown-review-table-mobile>
                    @foreach ($breakdowns as $breakdown)
                        @php
                            $runningMinutes = $breakdown->status === 'open' ? (int) $breakdown->breakdown_at?->diffInMinutes(now()) : null;
                            $downtimeMinutes = $breakdown->status === 'closed' ? (int) ($breakdown->downtime_minutes ?? 0) : (int) $runningMinutes;
                            $downtimeHours = intdiv($downtimeMinutes, 60);
                            $downtimeRemainderMinutes = $downtimeMinutes % 60;
                            $downtimeLabel = $downtimeHours.' Jam '.$downtimeRemainderMinutes.' Menit';
                            $partName = $breakdown->part_name_snapshot ?: ($breakdown->custom_part_name ?: '-');
                            $breakdownDateRaw = optional($breakdown->breakdown_at)->format('Y-m-d') ?? '';
                            $breakdownDateValue = optional($breakdown->breakdown_at)->format('Y-m-d H:i:s') ?? '';
                            $isHighDowntime = $downtimeMinutes >= 1440;
                        @endphp
                        <article
                            class="rounded-[12px] border border-[rgba(0,0,0,0.1)] bg-white p-[14px]"
                            data-breakdown-review-row
                            data-code="{{ strtolower($breakdown->breakdown_code) }}"
                            data-machine="{{ strtolower($breakdown->machine_name_snapshot) }}"
                            data-name="{{ strtolower($breakdown->machine_name_snapshot) }}"
                            data-part="{{ strtolower($partName) }}"
                            data-problem="{{ strtolower($breakdown->problem) }}"
                            data-status="{{ strtolower($breakdown->status) }}"
                            data-location="{{ strtolower($breakdown->location_name_snapshot) }}"
                            data-breakdown-date="{{ $breakdownDateValue }}"
                            data-breakdown-day="{{ $breakdownDateRaw }}"
                        >
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <p class="text-[12px] font-semibold text-[#615d59]">{{ $breakdown->breakdown_code }}</p>
                                <span class="inline-flex h-[25px] items-center whitespace-nowrap rounded-[9999px] border px-[11px] text-[12px] font-semibold leading-[1.2] {{ $breakdown->status === 'open' ? 'border-[#fed7aa] bg-[#fff7ed] text-[#9a3412]' : 'border-[#abefc6] bg-[#ecfdf3] text-[#067647]' }}">
                                    {{ strtoupper($breakdown->status) }}
                                </span>
                            </div>

                            <h3 class="mb-1 break-words text-[15px] font-bold text-[rgba(0,0,0,0.95)]">{{ $breakdown->machine_name_snapshot }}</h3>
                            <p class="text-[13px] text-[#615d59]">Part: {{ $partName }}</p>
                            <p class="truncate text-[13px] text-[#615d59]" title="{{ $breakdown->problem }}">Problem: {{ $breakdown->problem }}</p>
                            <p class="text-[13px] text-[#615d59]">Tanggal: {{ optional($breakdown->breakdown_at)->translatedFormat('d M Y, H:i') ?? '-' }}</p>
                            <p class="mb-[10px] text-[13px] font-semibold {{ $isHighDowntime ? 'text-[#a15c00]' : 'text-[rgba(0,0,0,0.95)]' }}">Downtime: {{ $downtimeLabel }}</p>

                            <a
                                href="{{ route('breakdown-review.show', $breakdown->id) }}"
                                class="inline-flex h-9 items-center justify-center rounded-[6px] border border-[rgba(0,0,0,0.1)] px-3 text-[12px] font-semibold text-[rgba(0,0,0,0.95)]"
                            >
                                Detail Breakdown
                            </a>
                        </article>
                    @endforeach
                </div>

                <div data-breakdown-review-pagination>
                    <x-ui.numeric-pagination
                        :paginator="$breakdowns"
                        summary-class="text-[13px] font-normal text-[#615d59]"
                        container-class="flex flex-col gap-4 border-t border-[rgba(0,0,0,0.1)] px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between"
                    />
                </div>
            @endif
        </x-ui.card>
    </section>
</x-layouts.app>
