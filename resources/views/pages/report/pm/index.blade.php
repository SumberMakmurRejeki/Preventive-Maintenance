<x-layouts.app
    title="Report PM"
    heading=""
    :actor-name="$actorName"
    :role="$role"
>
    <section class="mt-8 space-y-6 font-['NotionInter','Inter',ui-sans-serif,system-ui,sans-serif] text-[rgba(0,0,0,0.95)]" data-report-pm-page>
        @php
            $hasActiveFilters = collect([
                $filters['search'] ?? null,
                $filters['start_date'] ?? null,
                $filters['end_date'] ?? null,
                $filters['location_id'] ?? null,
                $filters['status'] ?? null,
                $filters['warning'] ?? null,
            ])->contains(fn ($value) => filled($value));
        @endphp

        <div class="space-y-6">
            <div class="flex items-center gap-2 text-[15px] font-medium text-[rgba(0,0,0,0.95)]">
                <span>Report</span>
                <span class="text-[#a39e98]">/</span>
                <span>Report PM</span>
            </div>

            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h2 class="text-[40px] font-bold leading-[1.1] tracking-[-0.8px] text-[rgba(0,0,0,0.95)]">Report PM</h2>
                    <p class="mt-2 text-[16px] font-normal leading-[1.5] text-[#615d59]">Pantau histori preventive maintenance, hasil pengecekan, status PM, dan warning mesin.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" data-history-open class="inline-flex h-11 items-center justify-center rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[18px] text-[14px] font-semibold text-[rgba(0,0,0,0.95)] transition hover:bg-[rgba(0,0,0,0.03)]">
                        Riwayat Export
                    </button>
                    <button type="button" data-export-trigger="pdf" class="inline-flex h-11 items-center justify-center gap-2 rounded-[6px] border border-[#f1b5b5] bg-white px-[18px] text-[14px] font-semibold text-[#b91c1c] transition hover:bg-[#fff5f5] disabled:cursor-not-allowed disabled:opacity-60">
                        <x-ui.icon name="arrow-down-tray" class="h-4 w-4" />
                        Export PDF
                    </button>
                    <button type="button" data-export-trigger="excel" class="inline-flex h-11 items-center justify-center gap-2 rounded-[6px] border border-[#b8efcc] bg-white px-[18px] text-[14px] font-semibold text-[#15803d] transition hover:bg-[#f5fff8] disabled:cursor-not-allowed disabled:opacity-60">
                        <x-ui.icon name="arrow-down-tray" class="h-4 w-4" />
                        Export Excel
                    </button>
                </div>
            </div>
        </div>

        @if ($loadError)
            <x-ui.alert variant="error">{{ $loadErrorMessage }}</x-ui.alert>
        @endif
        @if (session('flash_success'))
            <x-ui.alert variant="success">{{ session('flash_success') }}</x-ui.alert>
        @endif
        @if (session('flash_error'))
            <x-ui.alert variant="error">{{ session('flash_error') }}</x-ui.alert>
        @endif
        @if (session('flash_warning'))
            <x-ui.alert variant="warning">{{ session('flash_warning') }}</x-ui.alert>
        @endif
        @if ($errors->has('end_date'))
            <x-ui.alert variant="error">{{ $errors->first('end_date') }}</x-ui.alert>
        @endif

        <x-ui.card padding="p-0" class="table-card mx-auto w-full max-w-[1440px] overflow-hidden rounded-[12px] border border-[rgba(0,0,0,0.1)] bg-white shadow-[rgba(0,0,0,0.02)_0px_4px_18px]">
            <div class="table-toolbar border-b border-[rgba(0,0,0,0.1)] px-4 py-4">
                <form method="GET" action="{{ route('report-pm.index') }}" class="flex flex-col items-stretch gap-[10px] md:flex-wrap md:flex-row md:items-center md:gap-3" data-report-pm-filter-form>
                    <label class="relative block min-w-0 md:w-[380px]">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-[#615d59]">
                            <x-ui.icon name="magnifying-glass" class="h-[18px] w-[18px]" />
                        </span>
                        <input
                            type="search"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Cari kode mesin, nama mesin, part, atau standard..."
                            class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white py-0 pl-11 pr-9 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition placeholder:text-[#615d59] focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10"
                            data-report-pm-filter-search
                        >
                        <span class="pointer-events-none absolute inset-y-0 right-3 hidden items-center text-[#615d59]" data-report-pm-filter-search-loading>
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-[#0075de]/30 border-t-[#0075de]"></span>
                        </span>
                    </label>

                    <input
                        type="date"
                        name="start_date"
                        value="{{ $filters['start_date'] }}"
                        class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 md:w-[160px]"
                        data-report-pm-filter-start-date
                        aria-label="Tanggal Mulai"
                    >

                    <input
                        type="date"
                        name="end_date"
                        value="{{ $filters['end_date'] }}"
                        class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 md:w-[160px]"
                        data-report-pm-filter-end-date
                        aria-label="Tanggal Akhir"
                    >

                    <select
                        name="location_id"
                        class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 md:w-[180px]"
                        data-report-pm-filter-location
                    >
                        <option value="">Semua Lokasi</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((int) ($filters['location_id'] ?? 0) === $location->id)>{{ $location->location_name }}</option>
                        @endforeach
                    </select>

                    <select
                        name="status"
                        class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 md:w-[170px]"
                        data-report-pm-filter-status
                    >
                        <option value="">Semua Status</option>
                        <option value="scheduled" @selected($filters['status'] === 'scheduled')>Scheduled</option>
                        <option value="in_progress" @selected($filters['status'] === 'in_progress')>In Progress</option>
                        <option value="waiting_review" @selected($filters['status'] === 'waiting_review')>Waiting Review</option>
                        <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                        <option value="overdue" @selected($filters['status'] === 'overdue')>Overdue</option>
                        <option value="missed" @selected($filters['status'] === 'missed')>Missed</option>
                    </select>

                    <select
                        name="warning"
                        class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 md:w-[160px]"
                        data-report-pm-filter-warning
                    >
                        <option value="">Semua Warning</option>
                        <option value="warning" @selected($filters['warning'] === 'warning')>Ada Warning</option>
                        <option value="normal" @selected($filters['warning'] === 'normal')>Tidak ada</option>
                    </select>

                    <button
                        type="button"
                        class="ml-0 inline-flex h-10 items-center justify-center rounded-[6px] border border-[rgba(0,0,0,0.1)] px-4 text-[14px] font-medium text-[rgba(0,0,0,0.95)] transition hover:bg-[rgba(0,0,0,0.03)] md:ml-auto"
                        data-report-pm-filter-reset
                    >
                        Reset Filter
                    </button>
                </form>
            </div>

            @if ($rows->count() === 0)
                <div class="px-6 py-10">
                    <x-ui.empty-state
                        :title="$hasActiveFilters ? 'Tidak ada data Report PM ditemukan.' : 'Belum ada data Report PM.'"
                        :message="$hasActiveFilters ? 'Coba ubah filter atau reset filter.' : 'Data PM terjadwal, hasil eksekusi, dan approval akan tampil di sini.'"
                    />
                    @if ($hasActiveFilters)
                        <div class="mt-4 flex justify-center">
                            <button
                                type="button"
                                class="inline-flex h-10 items-center justify-center rounded-[6px] border border-[rgba(0,0,0,0.1)] px-4 text-[14px] font-medium text-[rgba(0,0,0,0.95)] transition hover:bg-[rgba(0,0,0,0.03)]"
                                data-report-pm-filter-empty-reset
                            >
                                Reset Filter
                            </button>
                        </div>
                    @endif
                </div>
            @else
                <div class="hidden overflow-x-auto lg:block" data-report-pm-table-desktop>
                    <table class="machine-table min-w-full table-fixed border-separate border-spacing-0 [&_th]:h-12 [&_th]:whitespace-nowrap [&_th]:px-4 [&_td]:min-h-[56px] [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle">
                        <colgroup>
                            <col style="width:56px">
                            <col style="width:120px">
                            <col style="width:110px">
                            <col style="width:190px">
                            <col style="width:170px">
                            <col style="width:130px">
                            <col style="width:115px">
                            <col style="width:120px">
                            <col style="width:130px">
                            <col style="width:140px">
                            <col style="width:140px">
                            <col style="width:88px">
                        </colgroup>
                        <thead class="bg-[#f6f5f4]">
                            <tr>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">No</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Tanggal PM</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Kode Mesin</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Nama Mesin</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Part Mesin</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Lokasi</th>
                                <th class="text-center text-[13px] font-semibold text-[#615d59]">Status PM</th>
                                <th class="text-center text-[13px] font-semibold text-[#615d59]">Warning</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Operator</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Tanggal Submit</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Tanggal Approve</th>
                                <th class="text-center text-[13px] font-semibold text-[#615d59]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            @foreach ($rows as $row)
                                @php
                                    $statusCode = strtolower((string) ($row->execution_status ?: $row->schedule_status));
                                    $statusLabel = $reportPmService->statusLabel($row);
                                    $warningCode = $reportPmService->warningLabel($row) === 'Warning' ? 'warning' : 'normal';
                                    $scheduledDateRaw = $row->scheduled_date ? \Illuminate\Support\Carbon::parse((string) $row->scheduled_date)->format('Y-m-d') : '';
                                    $submitDateRaw = $row->submitted_at ? \Illuminate\Support\Carbon::parse((string) $row->submitted_at)->format('Y-m-d') : '';
                                    $approveDateRaw = $row->approved_at ? \Illuminate\Support\Carbon::parse((string) $row->approved_at)->format('Y-m-d') : '';
                                    $statusBadgeClass = match ($statusCode) {
                                        'approved' => 'border-[#abefc6] bg-[#ecfdf3] text-[#067647]',
                                        'waiting_review', 'in_progress' => 'border-[#fed7aa] bg-[#fff7ed] text-[#9a3412]',
                                        'overdue', 'missed' => 'border-[#fecaca] bg-[#fff1f2] text-[#b42318]',
                                        default => 'border-[rgba(0,0,0,0.1)] bg-[#f6f5f4] text-[#615d59]',
                                    };
                                    $warningBadgeClass = $warningCode === 'warning'
                                        ? 'border-[#fed7aa] bg-[#fff7ed] text-[#9a3412]'
                                        : 'border-[#d1fadf] bg-[#f6fef9] text-[#067647]';
                                @endphp
                                <tr
                                    class="border-b border-[rgba(0,0,0,0.08)] text-[14px] text-[rgba(0,0,0,0.95)] transition hover:bg-[#f6f5f4]"
                                    data-report-pm-row
                                    data-code="{{ strtolower((string) ($row->machine_code ?? '-')) }}"
                                    data-name="{{ strtolower((string) ($row->machine_name ?? '-')) }}"
                                    data-part="{{ strtolower((string) ($row->part_name_snapshot ?? '-')) }}"
                                    data-standard="{{ strtolower((string) ($row->standard_name_snapshot ?? '-')) }}"
                                    data-location="{{ strtolower((string) ($row->location_name ?? '-')) }}"
                                    data-status="{{ $statusCode }}"
                                    data-warning="{{ $warningCode }}"
                                    data-scheduled-date="{{ $scheduledDateRaw }}"
                                    data-submit-date="{{ $submitDateRaw }}"
                                    data-approve-date="{{ $approveDateRaw }}"
                                >
                                    <td class="text-left">{{ $rows->firstItem() + $loop->index }}</td>
                                    <td class="text-left">{{ $reportPmService->dateTimeOrDash($row->scheduled_date, 'd M Y') }}</td>
                                    <td class="text-left font-semibold">{{ $row->machine_code ?? '-' }}</td>
                                    <td class="max-w-[190px] overflow-hidden text-ellipsis whitespace-nowrap text-left" title="{{ $row->machine_name ?? '-' }}">{{ $row->machine_name ?? '-' }}</td>
                                    <td class="max-w-[170px] whitespace-normal break-words leading-[1.4] text-left" title="{{ $row->part_name_snapshot ?? '-' }}">{{ $row->part_name_snapshot ?? '-' }}</td>
                                    <td class="text-left">{{ $row->location_name ?? '-' }}</td>
                                    <td class="text-center">
                                        <span class="inline-flex h-[25px] items-center whitespace-nowrap rounded-[9999px] border px-[11px] text-[12px] font-semibold leading-[1.2] {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="inline-flex h-[25px] items-center whitespace-nowrap rounded-[9999px] border px-[11px] text-[12px] font-semibold leading-[1.2] {{ $warningBadgeClass }}">{{ $warningCode === 'warning' ? 'Ada Warning' : 'Tidak ada' }}</span>
                                    </td>
                                    <td class="max-w-[130px] overflow-hidden text-ellipsis whitespace-nowrap text-left" title="{{ $row->operator_name_snapshot ?? '-' }}">{{ $row->operator_name_snapshot ?? '-' }}</td>
                                    <td class="text-left">{{ $reportPmService->dateTimeOrDash($row->submitted_at, 'd M Y H:i') }}</td>
                                    <td class="text-left">{{ $reportPmService->dateTimeOrDash($row->approved_at, 'd M Y H:i') }}</td>
                                    <td class="text-center">
                                        @if ($row->execution_id)
                                            <a href="{{ route('pm-review.show', $row->execution_id) }}" class="prime-icon-tooltip relative inline-flex h-9 w-9 items-center justify-center rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)] hover:text-[#0075de]" data-tooltip="Detail Report PM" aria-label="Detail Report PM">
                                                <x-ui.icon name="eye" class="h-[18px] w-[18px]" />
                                                <span class="sr-only">Detail Report PM</span>
                                            </a>
                                        @else
                                            <span class="text-[13px] text-[#615d59]">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-[10px] p-3 lg:hidden" data-report-pm-table-mobile>
                    @foreach ($rows as $row)
                        @php
                            $statusCode = strtolower((string) ($row->execution_status ?: $row->schedule_status));
                            $statusLabel = $reportPmService->statusLabel($row);
                            $warningCode = $reportPmService->warningLabel($row) === 'Warning' ? 'warning' : 'normal';
                            $scheduledDateRaw = $row->scheduled_date ? \Illuminate\Support\Carbon::parse((string) $row->scheduled_date)->format('Y-m-d') : '';
                            $submitDateRaw = $row->submitted_at ? \Illuminate\Support\Carbon::parse((string) $row->submitted_at)->format('Y-m-d') : '';
                            $approveDateRaw = $row->approved_at ? \Illuminate\Support\Carbon::parse((string) $row->approved_at)->format('Y-m-d') : '';
                            $statusBadgeClass = match ($statusCode) {
                                'approved' => 'border-[#abefc6] bg-[#ecfdf3] text-[#067647]',
                                'waiting_review', 'in_progress' => 'border-[#fed7aa] bg-[#fff7ed] text-[#9a3412]',
                                'overdue', 'missed' => 'border-[#fecaca] bg-[#fff1f2] text-[#b42318]',
                                default => 'border-[rgba(0,0,0,0.1)] bg-[#f6f5f4] text-[#615d59]',
                            };
                        @endphp
                        <article
                            class="rounded-[12px] border border-[rgba(0,0,0,0.1)] bg-white p-[14px]"
                            data-report-pm-row
                            data-code="{{ strtolower((string) ($row->machine_code ?? '-')) }}"
                            data-name="{{ strtolower((string) ($row->machine_name ?? '-')) }}"
                            data-part="{{ strtolower((string) ($row->part_name_snapshot ?? '-')) }}"
                            data-standard="{{ strtolower((string) ($row->standard_name_snapshot ?? '-')) }}"
                            data-location="{{ strtolower((string) ($row->location_name ?? '-')) }}"
                            data-status="{{ $statusCode }}"
                            data-warning="{{ $warningCode }}"
                            data-scheduled-date="{{ $scheduledDateRaw }}"
                            data-submit-date="{{ $submitDateRaw }}"
                            data-approve-date="{{ $approveDateRaw }}"
                        >
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <p class="text-[12px] font-semibold text-[#615d59]">{{ $row->machine_code ?? '-' }}</p>
                                <span class="inline-flex h-[25px] items-center whitespace-nowrap rounded-[9999px] border px-[11px] text-[12px] font-semibold leading-[1.2] {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
                            </div>
                            <h3 class="mb-1 break-words text-[15px] font-bold text-[rgba(0,0,0,0.95)]">{{ $row->machine_name ?? '-' }}</h3>
                            <p class="text-[13px] text-[#615d59]">Tanggal PM: {{ $reportPmService->dateTimeOrDash($row->scheduled_date, 'd M Y') }}</p>
                            <p class="text-[13px] text-[#615d59]">Lokasi: {{ $row->location_name ?? '-' }}</p>
                            <p class="text-[13px] text-[#615d59]">Warning: <span class="font-medium {{ $warningCode === 'warning' ? 'text-[#9a3412]' : 'text-[#067647]' }}">{{ $warningCode === 'warning' ? 'Ada Warning' : 'Tidak ada' }}</span></p>
                            <p class="text-[13px] text-[#615d59]">Operator: {{ $row->operator_name_snapshot ?? '-' }}</p>
                            <p class="text-[13px] text-[#615d59]">Submit: {{ $reportPmService->dateTimeOrDash($row->submitted_at, 'd M Y H:i') }}</p>
                            <p class="mb-[10px] text-[13px] text-[#615d59]">Approve: {{ $reportPmService->dateTimeOrDash($row->approved_at, 'd M Y H:i') }}</p>

                            @if ($row->execution_id)
                                <a href="{{ route('pm-review.show', $row->execution_id) }}" class="inline-flex h-9 items-center justify-center rounded-[6px] border border-[rgba(0,0,0,0.1)] px-3 text-[12px] font-semibold text-[rgba(0,0,0,0.95)]">Detail Report PM</a>
                            @else
                                <button type="button" class="inline-flex h-9 items-center justify-center rounded-[6px] border border-[rgba(0,0,0,0.1)] px-3 text-[12px] font-semibold text-[#615d59] opacity-60" disabled>Belum ada detail</button>
                            @endif
                        </article>
                    @endforeach
                </div>

                <div data-report-pm-pagination>
                    <span class="hidden">Menampilkan {{ $rows->firstItem() ?? 0 }}-{{ $rows->lastItem() ?? 0 }} dari total {{ $rows->total() }} data</span>
                    <x-ui.numeric-pagination
                        :paginator="$rows"
                        summary-class="text-[13px] font-normal text-[#615d59]"
                        container-class="flex flex-col gap-4 border-t border-[rgba(0,0,0,0.1)] px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between"
                    />
                </div>
            @endif
        </x-ui.card>
    </section>

    <div id="report-export-modal" class="fixed inset-0 z-[120] hidden items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-2xl border border-[var(--color-prime-border)] bg-white px-6 py-7 shadow-[0_24px_44px_-26px_rgba(15,23,42,0.35)]">
            <div id="report-export-processing" class="text-center">
                <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-[var(--color-prime-primary-soft)] border-t-[var(--color-prime-primary)]"></div>
                <h4 class="mt-5 text-3xl font-semibold tracking-[-0.03em] text-[var(--color-prime-ink)]">Memproses Export</h4>
                <p class="mt-2 text-base text-[var(--color-prime-muted)]">Harap tunggu, sedang menyiapkan <span id="report-export-type-label" class="font-semibold">PDF</span>...</p>
            </div>
            <div id="report-export-success" class="hidden text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[var(--color-prime-success-soft)] text-[var(--color-prime-success)]">
                    <x-ui.icon name="shield-check" class="h-6 w-6" />
                </div>
                <h4 class="mt-4 text-3xl font-semibold tracking-[-0.03em] text-[var(--color-prime-ink)]">Export Berhasil</h4>
                <p class="mt-2 text-base text-[var(--color-prime-muted)]">Report PM berhasil dibuat.</p>
                <div class="mt-6 flex items-center justify-center gap-2">
                    <button type="button" data-export-close class="rounded-xl border border-[var(--color-prime-border)] px-4 py-2.5 text-sm font-semibold text-[var(--color-prime-ink)]">Tutup</button>
                    <a href="#" id="report-export-download-link" class="rounded-xl bg-[var(--color-prime-primary)] px-4 py-2.5 text-sm font-semibold text-white">Unduh File</a>
                </div>
            </div>
            <div id="report-export-failed" class="hidden text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[var(--color-prime-danger-soft)] text-[var(--color-prime-danger)]">
                    <x-ui.icon name="x-mark" class="h-6 w-6" />
                </div>
                <h4 class="mt-4 text-3xl font-semibold tracking-[-0.03em] text-[var(--color-prime-ink)]">Export Gagal</h4>
                <p id="report-export-failed-text" class="mt-2 text-base text-[var(--color-prime-muted)]">Report PM gagal dibuat. Silakan coba kembali.</p>
                <div class="mt-6">
                    <button type="button" data-export-close class="rounded-xl border border-[var(--color-prime-border)] px-4 py-2.5 text-sm font-semibold text-[var(--color-prime-ink)]">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div id="report-history-modal" class="fixed inset-0 z-[130] hidden items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm">
        <div class="w-full max-w-6xl rounded-2xl border border-[var(--color-prime-border)] bg-white shadow-[0_24px_44px_-26px_rgba(15,23,42,0.35)]">
            <div class="flex items-center justify-between border-b border-[var(--color-prime-border)] px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-[var(--color-prime-ink)]">Riwayat Export</h3>
                    <p class="mt-1 text-sm text-[var(--color-prime-muted)]">Lihat status export PM dan breakdown yang pernah dibuat.</p>
                </div>
                <button type="button" data-history-close class="rounded-xl border border-[var(--color-prime-border)] px-3 py-2 text-sm font-semibold text-[var(--color-prime-ink)]">Tutup</button>
            </div>
            <div class="max-h-[70vh] overflow-auto p-6">
                <table class="min-w-full divide-y divide-[var(--color-prime-border)]">
                    <thead class="bg-[var(--color-prime-warm-white)]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-[var(--color-prime-muted)]">Waktu Request</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-[var(--color-prime-muted)]">Jenis Report</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-[var(--color-prime-muted)]">File</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-[var(--color-prime-muted)]">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-[var(--color-prime-muted)]">Requested By</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-[var(--color-prime-muted)]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-prime-border)]">
                        @forelse ($exports as $export)
                            <tr>
                                <td class="px-4 py-3 text-sm">{{ optional($export->requested_at)->format('Y-m-d H:i:s') ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm">{{ strtoupper($export->report_type) }}</td>
                                <td class="px-4 py-3 text-sm">{{ strtoupper($export->file_type) }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <x-ui.badge :variant="$export->status === 'completed' ? 'success' : ($export->status === 'failed' ? 'danger' : 'warning')">{{ strtoupper($export->status) }}</x-ui.badge>
                                    @if ($export->status === 'failed' && $export->failed_message)
                                        <p class="mt-1 text-xs text-[var(--color-prime-danger)]">{{ $export->failed_message }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $export->requested_by_name_snapshot ?? '-' }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    @if ($export->status === 'completed')
                                        @if ($export->report_type === 'pm')
                                            <a href="{{ route('report-exports.download', $export->id) }}" class="rounded-lg border border-[var(--color-prime-border)] px-3 py-2 text-xs font-semibold text-[var(--color-prime-primary)]">Download</a>
                                        @else
                                            <a href="{{ route('report-breakdown.download', $export->id) }}" class="rounded-lg border border-[var(--color-prime-border)] px-3 py-2 text-xs font-semibold text-[var(--color-prime-primary)]">Download</a>
                                        @endif
                                    @elseif ($export->status === 'processing')
                                        <span class="text-xs text-[var(--color-prime-muted)]">Masih diproses</span>
                                    @else
                                        <span class="text-xs text-[var(--color-prime-muted)]">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-[var(--color-prime-muted)]">Belum ada riwayat export.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('report-export-modal');
            const historyModal = document.getElementById('report-history-modal');
            const stateProcessing = document.getElementById('report-export-processing');
            const stateSuccess = document.getElementById('report-export-success');
            const stateFailed = document.getElementById('report-export-failed');
            const failedText = document.getElementById('report-export-failed-text');
            const typeLabel = document.getElementById('report-export-type-label');
            const downloadLink = document.getElementById('report-export-download-link');
            const filterForm = document.querySelector('[data-report-pm-filter-form]');
            const searchInput = document.querySelector('[data-report-pm-filter-search]');
            const startDateInput = document.querySelector('[data-report-pm-filter-start-date]');
            const endDateInput = document.querySelector('[data-report-pm-filter-end-date]');
            const locationSelect = document.querySelector('[data-report-pm-filter-location]');
            const statusSelect = document.querySelector('[data-report-pm-filter-status]');
            const warningSelect = document.querySelector('[data-report-pm-filter-warning]');
            const resetButton = document.querySelector('[data-report-pm-filter-reset]');
            const emptyResetButton = document.querySelector('[data-report-pm-filter-empty-reset]');
            const searchLoading = document.querySelector('[data-report-pm-filter-search-loading]');
            const exportButtons = document.querySelectorAll('[data-export-trigger]');
            const historyOpenButton = document.querySelector('[data-history-open]');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            let isExporting = false;
            let debounceTimer;

            if (modal && modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }

            if (historyModal && historyModal.parentElement !== document.body) {
                document.body.appendChild(historyModal);
            }

            const openModal = () => {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            const openHistoryModal = () => {
                historyModal?.classList.remove('hidden');
                historyModal?.classList.add('flex');
            };

            const closeHistoryModal = () => {
                historyModal?.classList.add('hidden');
                historyModal?.classList.remove('flex');
            };

            const setState = (type) => {
                stateProcessing.classList.toggle('hidden', type !== 'processing');
                stateSuccess.classList.toggle('hidden', type !== 'success');
                stateFailed.classList.toggle('hidden', type !== 'failed');
            };

            const formPayload = () => {
                return filterForm instanceof HTMLFormElement
                    ? new FormData(filterForm)
                    : new FormData();
            };

            const setExportButtonsDisabled = (disabled) => {
                exportButtons.forEach((button) => {
                    button.disabled = disabled;
                });
            };

            const submitFilterForm = () => {
                if (!(filterForm instanceof HTMLFormElement)) {
                    return;
                }

                if (searchLoading) {
                    searchLoading.classList.add('hidden');
                    searchLoading.classList.remove('flex');
                }

                filterForm.requestSubmit();
            };

            document.querySelectorAll('[data-export-close]').forEach((button) => {
                button.addEventListener('click', closeModal);
            });

            document.querySelectorAll('[data-history-close]').forEach((button) => {
                button.addEventListener('click', closeHistoryModal);
            });

            historyOpenButton?.addEventListener('click', openHistoryModal);

            modal?.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });

            historyModal?.addEventListener('click', (event) => {
                if (event.target === historyModal) {
                    closeHistoryModal();
                }
            });

            const exportNow = async (type) => {
                if (isExporting) {
                    return;
                }

                isExporting = true;
                setExportButtonsDisabled(true);
                typeLabel.textContent = type.toUpperCase();
                setState('processing');
                openModal();

                const endpoint = type === 'pdf'
                    ? '{{ route('report-pm.export-pdf') }}'
                    : '{{ route('report-pm.export-excel') }}';

                try {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                        body: formPayload(),
                    });

                    const payload = await response.json();

                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'Report PM gagal dibuat. Silakan coba kembali.');
                    }

                    downloadLink.href = payload.download_url;
                    setState('success');
                } catch (error) {
                    failedText.textContent = error.message || 'Report PM gagal dibuat. Silakan coba kembali.';
                    setState('failed');
                } finally {
                    isExporting = false;
                    setExportButtonsDisabled(false);
                }
            };

            exportButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    exportNow(button.getAttribute('data-export-trigger'));
                });
            });

            if (searchInput instanceof HTMLInputElement) {
                searchInput.addEventListener('input', () => {
                    if (searchLoading) {
                        searchLoading.classList.remove('hidden');
                        searchLoading.classList.add('flex');
                    }

                    window.clearTimeout(debounceTimer);
                    debounceTimer = window.setTimeout(() => {
                        submitFilterForm();
                    }, 300);
                });
            }

            startDateInput?.addEventListener('change', submitFilterForm);
            endDateInput?.addEventListener('change', submitFilterForm);
            locationSelect?.addEventListener('change', submitFilterForm);
            statusSelect?.addEventListener('change', submitFilterForm);
            warningSelect?.addEventListener('change', submitFilterForm);
            resetButton?.addEventListener('click', () => {
                window.location.href = '{{ route('report-pm.index') }}';
            });
            emptyResetButton?.addEventListener('click', () => {
                window.location.href = '{{ route('report-pm.index') }}';
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeModal();
                    closeHistoryModal();
                }
            });
        });
    </script>
</x-layouts.app>
