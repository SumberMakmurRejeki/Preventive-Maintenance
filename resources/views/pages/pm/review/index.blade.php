<x-layouts.app
    title="PM Review"
    heading="PM Management / PM Review"
    :actor-name="$actorName"
    :role="$role"
>
    <section class="mt-8 space-y-6 font-['NotionInter','Inter',ui-sans-serif,system-ui,sans-serif] text-[rgba(0,0,0,0.95)]" data-pm-review-page>
        <div class="space-y-6">
            <div class="flex items-center gap-2 text-[15px] font-medium text-[rgba(0,0,0,0.95)]">
                <span>PM Management</span>
                <span class="text-[#a39e98]">/</span>
                <span>PM Review</span>
            </div>

            <div>
                <h2 class="text-[40px] font-bold leading-[1.1] tracking-[-0.8px] text-[rgba(0,0,0,0.95)]">PM Review</h2>
                <p class="mt-2 text-[16px] font-normal leading-[1.5] text-[#615d59]">Validasi, evaluasi, dan setujui hasil PM operator.</p>
            </div>
        </div>

        @if (session('flash_success'))
            <x-ui.alert variant="success" class="rounded-[12px] border border-[#b8efcc] bg-[#e9fbf1] px-4 py-3 text-[14px] text-[#0f8a3b]">{{ session('flash_success') }}</x-ui.alert>
        @endif
        @if (session('flash_error'))
            <x-ui.alert variant="error">{{ session('flash_error') }}</x-ui.alert>
        @endif

        <x-ui.card padding="p-0" class="table-card mx-auto w-full max-w-[1440px] overflow-hidden rounded-[12px] border border-[rgba(0,0,0,0.1)] bg-white shadow-[rgba(0,0,0,0.02)_0px_4px_18px]">
            <div class="table-toolbar border-b border-[rgba(0,0,0,0.1)] px-4 py-4">
                <form class="flex flex-col items-stretch gap-[10px] md:flex-wrap md:flex-row md:items-center md:gap-3" data-pm-review-filter-form>
                    <label class="relative block min-w-0 md:w-[320px]">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-[#615d59]"><x-ui.icon name="magnifying-glass" class="h-[18px] w-[18px]" /></span>
                        <input type="search" value="{{ $filters['search'] }}" placeholder="Cari mesin atau operator..." class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white py-0 pl-11 pr-9 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition placeholder:text-[#615d59] focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10" data-pm-review-filter-search>
                        <span class="pointer-events-none absolute inset-y-0 right-3 hidden items-center text-[#615d59]" data-pm-review-filter-search-loading><span class="h-4 w-4 animate-spin rounded-full border-2 border-[#0075de]/30 border-t-[#0075de]"></span></span>
                    </label>

                    <select class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 md:w-[180px]" data-pm-review-filter-status>
                        <option value="">Status</option>
                        <option value="waiting_review" @selected($filters['status'] === 'waiting_review')>Waiting Review</option>
                        <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                    </select>

                    <select class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 md:w-[180px]" data-pm-review-filter-location>
                        <option value="">Lokasi</option>
                        @foreach ($locations as $location)
                            <option value="{{ strtolower($location->location_name) }}" @selected((int) $filters['location_id'] === $location->id)>{{ $location->location_name }}</option>
                        @endforeach
                    </select>

                    <input type="date" value="{{ $filters['start_date'] }}" class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 md:w-[160px]" data-pm-review-filter-start-date aria-label="Dari Tanggal">
                    <input type="date" value="{{ $filters['end_date'] }}" class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 md:w-[160px]" data-pm-review-filter-end-date aria-label="Sampai Tanggal">

                    <button type="button" class="ml-0 inline-flex h-10 items-center justify-center rounded-[6px] border border-[rgba(0,0,0,0.1)] px-4 text-[14px] font-medium text-[rgba(0,0,0,0.95)] transition hover:bg-[rgba(0,0,0,0.03)] md:ml-auto" data-pm-review-filter-reset>Reset Filter</button>
                </form>
            </div>

            <div class="hidden px-6 py-10" data-pm-review-empty-filter>
                <x-ui.empty-state title="Tidak ada hasil PM ditemukan." message="Coba ubah kata kunci atau reset filter." />
                <div class="mt-4 flex justify-center">
                    <button type="button" class="inline-flex h-10 items-center justify-center rounded-[6px] border border-[rgba(0,0,0,0.1)] px-4 text-[14px] font-medium text-[rgba(0,0,0,0.95)] transition hover:bg-[rgba(0,0,0,0.03)]" data-pm-review-filter-empty-reset>Reset Filter</button>
                </div>
            </div>

            @if ($executions->count() === 0)
                <div class="px-6 py-10">
                    <x-ui.empty-state title="Belum ada hasil PM untuk direview." message="Hasil PM yang dikirim operator akan tampil di sini." />
                </div>
            @else
                <div class="hidden overflow-x-auto lg:block" data-pm-review-table-desktop>
                    <table class="machine-table min-w-full table-fixed border-separate border-spacing-0 [&_th]:h-12 [&_th]:whitespace-nowrap [&_th]:px-4 [&_td]:h-14 [&_td]:px-4 [&_td]:align-middle">
                        <colgroup>
                            <col style="width:56px"><col style="width:120px"><col style="width:130px"><col style="width:240px"><col style="width:140px"><col style="width:160px"><col style="width:140px"><col style="width:120px"><col style="width:160px"><col style="width:100px">
                        </colgroup>
                        <thead class="bg-[#f6f5f4]">
                            <tr>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">No</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-[rgba(0,0,0,0.95)]" data-sort-trigger data-sort-group="pm-review" data-sort-field="scheduledDate" aria-label="Urutkan Tanggal PM">
                                        Tanggal PM
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="pm-review" data-sort-field="scheduledDate">↕</span>
                                    </button>
                                </th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-[rgba(0,0,0,0.95)]" data-sort-trigger data-sort-group="pm-review" data-sort-field="code" aria-label="Urutkan Kode Mesin">
                                        Kode Mesin
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="pm-review" data-sort-field="code">↕</span>
                                    </button>
                                </th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-[rgba(0,0,0,0.95)]" data-sort-trigger data-sort-group="pm-review" data-sort-field="name" aria-label="Urutkan Nama Mesin">
                                        Nama Mesin
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="pm-review" data-sort-field="name">↕</span>
                                    </button>
                                </th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Lokasi</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-[rgba(0,0,0,0.95)]" data-sort-trigger data-sort-group="pm-review" data-sort-field="operator" aria-label="Urutkan Nama Operator">
                                        Nama Operator
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="pm-review" data-sort-field="operator">↕</span>
                                    </button>
                                </th>
                                <th class="text-center text-[13px] font-semibold text-[#615d59]">Status PM</th>
                                <th class="text-center text-[13px] font-semibold text-[#615d59]">Warning</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-[rgba(0,0,0,0.95)]" data-sort-trigger data-sort-group="pm-review" data-sort-field="submittedDate" aria-label="Urutkan Tanggal Submit">
                                        Tanggal Submit
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="pm-review" data-sort-field="submittedDate">↕</span>
                                    </button>
                                </th>
                                <th class="text-center text-[13px] font-semibold text-[#615d59]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            @foreach ($executions as $execution)
                                @php
                                    $scheduledDateRaw = optional($execution->scheduleDate?->scheduled_date)->format('Y-m-d') ?? '';
                                    $submittedDateRaw = optional($execution->submitted_at)->format('Y-m-d') ?? '';
                                    $machineCode = $execution->machine?->machine_code ?? '-';
                                    $machineName = $execution->machine?->machine_name ?? '-';
                                    $locationName = $execution->machine?->location?->location_name ?? '-';
                                    $operatorName = $execution->operator_name_snapshot ?? '-';
                                    $warningCount = (int) $execution->warning_count;
                                @endphp
                                <tr class="border-b border-[rgba(0,0,0,0.08)] text-[14px] text-[rgba(0,0,0,0.95)] transition hover:bg-[#f6f5f4]" data-pm-review-row data-code="{{ strtolower($machineCode) }}" data-name="{{ strtolower($machineName) }}" data-location="{{ strtolower($locationName) }}" data-operator="{{ strtolower($operatorName) }}" data-status="{{ $execution->status }}" data-scheduled-date="{{ $scheduledDateRaw }}" data-submitted-date="{{ $submittedDateRaw }}">
                                    <td class="text-left">{{ $executions->firstItem() + $loop->index }}</td>
                                    <td class="text-left">{{ $scheduledDateRaw !== '' ? \Carbon\Carbon::parse($scheduledDateRaw)->translatedFormat('d M Y') : '-' }}</td>
                                    <td class="text-left">{{ $machineCode }}</td>
                                    <td class="max-w-[240px] overflow-hidden text-ellipsis whitespace-nowrap text-left">{{ $machineName }}</td>
                                    <td class="text-left">{{ $locationName }}</td>
                                    <td class="max-w-[160px] overflow-hidden text-ellipsis whitespace-nowrap text-left">{{ $operatorName }}</td>
                                    <td class="text-center">
                                        <span class="inline-flex h-[25px] items-center whitespace-nowrap rounded-[9999px] border px-[11px] text-[12px] font-semibold leading-[1.2] tracking-[0.125px] {{ $execution->status === 'approved' ? 'border-[#abefc6] bg-[#ecfdf3] text-[#067647]' : 'border-[#fed7aa] bg-[#fff7ed] text-[#9a3412]' }}">{{ $execution->status === 'approved' ? 'Approved' : 'Menunggu Review' }}</span>
                                    </td>
                                    <td class="text-center">
                                        @if ($warningCount > 0)
                                            <span class="inline-flex h-[25px] items-center whitespace-nowrap rounded-[9999px] border border-[#fed7aa] bg-[#fff7ed] px-[11px] text-[12px] font-semibold leading-[1.2] tracking-[0.125px] text-[#9a3412]">{{ $warningCount }} Warning</span>
                                        @else
                                            <span class="inline-flex h-[25px] items-center whitespace-nowrap rounded-[9999px] border border-[#d1fadf] bg-[#f6fef9] px-[11px] text-[12px] font-semibold leading-[1.2] tracking-[0.125px] text-[#067647]">Tidak ada Warning</span>
                                        @endif
                                    </td>
                                    <td class="text-left">{{ optional($execution->submitted_at)->translatedFormat('d M Y H:i') ?? '-' }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('pm-review.show', $execution->id) }}" class="prime-icon-tooltip relative inline-flex h-9 w-9 items-center justify-center rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)] hover:text-[#0075de]" data-tooltip="Detail Review">
                                            <x-ui.icon name="eye" class="h-[18px] w-[18px]" />
                                            <span class="sr-only">Lihat detail review</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-[10px] p-3 lg:hidden" data-pm-review-table-mobile>
                    @foreach ($executions as $execution)
                        @php
                            $scheduledDateRaw = optional($execution->scheduleDate?->scheduled_date)->format('Y-m-d') ?? '';
                            $submittedDateRaw = optional($execution->submitted_at)->format('Y-m-d') ?? '';
                            $machineCode = $execution->machine?->machine_code ?? '-';
                            $machineName = $execution->machine?->machine_name ?? '-';
                            $locationName = $execution->machine?->location?->location_name ?? '-';
                            $operatorName = $execution->operator_name_snapshot ?? '-';
                            $warningCount = (int) $execution->warning_count;
                        @endphp
                        <article class="rounded-[12px] border border-[rgba(0,0,0,0.1)] bg-white p-[14px]" data-pm-review-row data-code="{{ strtolower($machineCode) }}" data-name="{{ strtolower($machineName) }}" data-location="{{ strtolower($locationName) }}" data-operator="{{ strtolower($operatorName) }}" data-status="{{ $execution->status }}" data-scheduled-date="{{ $scheduledDateRaw }}" data-submitted-date="{{ $submittedDateRaw }}">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <p class="text-[12px] font-semibold text-[#615d59]">{{ $machineCode }}</p>
                                <span class="inline-flex h-[25px] items-center whitespace-nowrap rounded-[9999px] border px-[11px] text-[12px] font-semibold leading-[1.2] tracking-[0.125px] {{ $execution->status === 'approved' ? 'border-[#abefc6] bg-[#ecfdf3] text-[#067647]' : 'border-[#fed7aa] bg-[#fff7ed] text-[#9a3412]' }}">{{ $execution->status === 'approved' ? 'Approved' : 'Menunggu Review' }}</span>
                            </div>
                            <h3 class="mb-1 break-words text-[15px] font-bold text-[rgba(0,0,0,0.95)]">{{ $machineName }}</h3>
                            <p class="text-[13px] text-[#615d59]">Tanggal PM: {{ $scheduledDateRaw !== '' ? \Carbon\Carbon::parse($scheduledDateRaw)->translatedFormat('d M Y') : '-' }}</p>
                            <p class="text-[13px] text-[#615d59]">Lokasi: {{ $locationName }}</p>
                            <p class="text-[13px] text-[#615d59]">Operator: {{ $operatorName }}</p>
                            <p class="text-[13px] text-[#615d59]">Warning: {{ $warningCount > 0 ? $warningCount . ' Warning' : 'Aman' }}</p>
                            <p class="mb-[10px] text-[13px] text-[#615d59]">Tanggal Submit: {{ optional($execution->submitted_at)->translatedFormat('d M Y H:i') ?? '-' }}</p>
                            <a href="{{ route('pm-review.show', $execution->id) }}" class="inline-flex h-9 items-center justify-center rounded-[6px] border border-[rgba(0,0,0,0.1)] px-3 text-[12px] font-semibold text-[rgba(0,0,0,0.95)]">Detail Review</a>
                        </article>
                    @endforeach
                </div>

                <div data-pm-review-pagination>
                    <x-ui.numeric-pagination :paginator="$executions" summary-class="text-[13px] font-normal text-[#615d59]" container-class="flex flex-col gap-4 border-t border-[rgba(0,0,0,0.1)] px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between" />
                </div>
            @endif
        </x-ui.card>
    </section>
</x-layouts.app>
