<x-layouts.app
    title="Master PM Checksheet PRIME"
    heading="Master PM Checksheet"
    :actor-name="$actorName"
    :role="$role"
>
    <section class="mt-8 space-y-6 font-['NotionInter','Inter',ui-sans-serif,system-ui,sans-serif] text-[rgba(0,0,0,0.95)]" data-checksheet-page>
        <div class="space-y-6">
            <div class="flex items-center gap-2 text-[15px] font-medium text-[rgba(0,0,0,0.95)]">
                <span>PM Management</span>
                <span class="text-[#a39e98]">/</span>
                <span>Master PM Checksheet</span>
            </div>

            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-[40px] font-bold leading-[1.1] tracking-[-0.8px] text-[rgba(0,0,0,0.95)]">Master PM Checksheet</h2>
                    <p class="mt-2 text-[16px] font-normal leading-[1.5] text-[#615d59]">Kelola standard pengecekan dan jadwal Preventive Maintenance mesin.</p>
                </div>
                <a href="{{ route('master-checksheet.create') }}" class="inline-flex h-[42px] w-full items-center justify-center rounded-[6px] bg-[#0075de] px-[18px] text-[14px] font-semibold text-white transition hover:bg-[#005bab] lg:h-11 lg:w-auto">Tambah Checksheet</a>
            </div>
        </div>

        @if (session('flash_success'))
            <x-ui.alert variant="success" class="rounded-[12px] border border-[#b8efcc] bg-[#e9fbf1] px-4 py-3 text-[14px] text-[#0f8a3b]">{{ session('flash_success') }}</x-ui.alert>
        @endif

        @if ($checksheets->count() === 0)
            <x-ui.card class="table-card mx-auto w-full max-w-[1440px] rounded-[12px] border border-[rgba(0,0,0,0.1)] bg-white shadow-[rgba(0,0,0,0.02)_0px_4px_18px]">
                <x-ui.empty-state title="Belum ada checksheet" message="Tambahkan checksheet pertama untuk memulai standard PM per mesin." />
            </x-ui.card>
        @else
            <x-ui.card padding="p-0" class="table-card mx-auto w-full max-w-[1440px] overflow-hidden rounded-[12px] border border-[rgba(0,0,0,0.1)] bg-white shadow-[rgba(0,0,0,0.02)_0px_4px_18px]">
                <div class="table-toolbar border-b border-[rgba(0,0,0,0.1)] px-4 py-4">
                    <form class="flex flex-col items-stretch gap-[10px] md:items-center md:gap-3 lg:flex-row lg:items-center lg:gap-3" data-checksheet-filter-form>
                        <label class="relative block md:w-[320px]">
                            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-[#615d59]"><x-ui.icon name="magnifying-glass" class="h-[18px] w-[18px]" /></span>
                            <input type="search" id="checksheetSearch" value="{{ $filters['search'] }}" placeholder="Cari kode atau nama..." class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white py-0 pl-11 pr-9 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition placeholder:text-[#615d59] focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10" data-checksheet-filter-search>
                            <span class="pointer-events-none absolute inset-y-0 right-3 hidden items-center text-[#615d59]" data-checksheet-filter-search-loading><span class="h-4 w-4 animate-spin rounded-full border-2 border-[#0075de]/30 border-t-[#0075de]"></span></span>
                        </label>

                        <select id="checksheetStatus" class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 md:w-[160px]" data-checksheet-filter-status>
                            <option value="">Semua Status</option>
                            <option value="aktif" @selected($filters['status'] === 'active')>Aktif</option>
                            <option value="nonaktif" @selected($filters['status'] === 'inactive')>Nonaktif</option>
                        </select>
                        <select id="checksheetLocation" class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 md:w-[160px]" data-checksheet-filter-location>
                            <option value="">Semua Lokasi</option>
                            @foreach ($locations as $location)
                                <option value="{{ strtolower($location->location_name) }}" @selected((int) ($filters['location_id'] ?? 0) === $location->id)>{{ $location->location_name }}</option>
                            @endforeach
                        </select>
                        <select id="checksheetMachine" class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 md:w-[160px]" data-checksheet-filter-machine>
                            <option value="">Semua Mesin</option>
                            @foreach ($machines as $machine)
                                <option value="{{ strtolower($machine->machine_code) }}" @selected((int) ($filters['machine_id'] ?? 0) === $machine->id)>{{ $machine->machine_code }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="ml-0 inline-flex h-10 items-center justify-center rounded-[6px] border border-[rgba(0,0,0,0.1)] px-4 text-[14px] font-medium text-[rgba(0,0,0,0.95)] transition hover:bg-[rgba(0,0,0,0.03)] md:ml-auto" data-checksheet-filter-reset>Reset Filter</button>
                    </form>
                </div>

                <div class="hidden px-6 py-10" data-checksheet-empty-filter>
                    <x-ui.empty-state title="Checksheet yang kamu cari tidak ditemukan" message="Coba ubah kata kunci atau reset filter." />
                </div>

                <div class="hidden overflow-x-auto lg:block" data-checksheet-table-desktop>
                    <table class="min-w-full table-fixed border-separate border-spacing-0 [&_th]:h-14 [&_th]:whitespace-nowrap [&_th]:px-4 [&_td]:h-14 [&_td]:whitespace-nowrap [&_td]:px-4 [&_td]:align-middle">
                        <colgroup>
                            <col style="width:56px"><col style="width:140px"><col style="width:220px"><col style="width:110px"><col style="width:90px"><col style="width:90px"><col style="width:110px"><col style="width:120px"><col style="width:160px">
                        </colgroup>
                        <thead class="bg-[#f6f5f4]">
                            <tr>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">No</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-[rgba(0,0,0,0.95)]" data-sort-trigger data-sort-group="checksheet" data-sort-field="code" aria-label="Urutkan Kode Checksheet">
                                        Kode
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="checksheet" data-sort-field="code">↕</span>
                                    </button>
                                </th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-[rgba(0,0,0,0.95)]" data-sort-trigger data-sort-group="checksheet" data-sort-field="name" aria-label="Urutkan Nama Checksheet">
                                        Nama Cheksheet
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="checksheet" data-sort-field="name">↕</span>
                                    </button>
                                </th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Mesin</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Part</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Standard</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Jadwal</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Status</th>
                                <th class="text-center text-[13px] font-semibold text-[#615d59]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            @foreach ($checksheets as $checksheet)
                                @php
                                    $machineCount = $checksheet->machineAssignments->count();
                                    $partCount = $checksheet->machineAssignments->sum(fn ($assignment) => $assignment->parts->count());
                                    $standardCount = $checksheet->machineAssignments->sum(fn ($assignment) => $assignment->parts->sum(fn ($part) => $part->standards->count()));
                                    $firstSchedule = $checksheet->machineAssignments->first()?->schedules->first();
                                    $scheduleLabel = $firstSchedule ? ucfirst($firstSchedule->frequency_type) : '-';
                                    $firstMachineCode = strtolower((string) ($checksheet->machineAssignments->first()?->machine?->machine_code ?? ''));
                                    $firstLocationName = strtolower((string) ($checksheet->machineAssignments->first()?->machine?->location?->location_name ?? ''));
                                @endphp
                                <tr class="border-b border-[rgba(0,0,0,0.08)] text-[14px] text-[rgba(0,0,0,0.95)] transition hover:bg-[#f6f5f4]" data-checksheet-row data-code="{{ strtolower($checksheet->checksheet_code) }}" data-name="{{ strtolower($checksheet->checksheet_name) }}" data-status="{{ $checksheet->is_active ? 'aktif' : 'nonaktif' }}" data-location="{{ $firstLocationName }}" data-machine="{{ $firstMachineCode }}">
                                    <td>{{ $checksheets->firstItem() + $loop->index }}</td>
                                    <td>{{ $checksheet->checksheet_code }}</td>
                                    <td class="!whitespace-normal break-words leading-[1.35]">{{ $checksheet->checksheet_name }}</td>
                                    <td>{{ $machineCount }} Mesin</td>
                                    <td>{{ $partCount }} Part</td>
                                    <td>{{ $standardCount }} Standard</td>
                                    <td>{{ $scheduleLabel }}</td>
                                    <td><span class="inline-flex min-h-7 items-center rounded-[9999px] px-3 py-1 text-[12px] font-semibold tracking-[0.125px] {{ $checksheet->is_active ? 'bg-[#e9fbf1] text-[#0f8a3b]' : 'bg-[#fff4df] text-[#a15c00]' }}">{{ $checksheet->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                    <td>
                                        <div class="flex items-center justify-center gap-1.5">
                                            <a href="{{ route('master-checksheet.show', $checksheet->id) }}" class="prime-icon-tooltip relative h-9 w-9 rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)]" data-tooltip="Lihat detail">
                                                <x-ui.icon name="eye" class="h-[18px] w-[18px]" />
                                                <span class="sr-only">Lihat detail checksheet</span>
                                            </a>
                                            <a href="{{ route('master-checksheet.edit', $checksheet->id) }}" class="prime-icon-tooltip relative h-9 w-9 rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)] hover:text-[#0075de]" data-tooltip="Edit checksheet">
                                                <x-ui.icon name="pencil-square" class="h-[18px] w-[18px]" />
                                                <span class="sr-only">Edit checksheet</span>
                                            </a>
                                            @if ($checksheet->is_active)
                                                <button type="button" data-checksheet-status-open data-checksheet-status-url="{{ route('master-checksheet.deactivate', $checksheet->id) }}" data-checksheet-status-label="Nonaktifkan Checksheet" data-checksheet-status-description="Checksheet akan dinonaktifkan dan tidak bisa digunakan." data-checksheet-status-button="Nonaktifkan" data-checksheet-status-variant="warning" class="prime-icon-tooltip relative h-9 w-9 rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)] hover:text-[#b86b00]" data-tooltip="Nonaktifkan checksheet">
                                                    <x-ui.icon name="power" class="h-[18px] w-[18px]" />
                                                    <span class="sr-only">Nonaktifkan checksheet</span>
                                                </button>
                                            @else
                                                <button type="button" data-checksheet-status-open data-checksheet-status-url="{{ route('master-checksheet.activate', $checksheet->id) }}" data-checksheet-status-label="Aktifkan Checksheet" data-checksheet-status-description="Checksheet akan diaktifkan kembali dan bisa digunakan." data-checksheet-status-button="Aktifkan" data-checksheet-status-variant="success" class="prime-icon-tooltip relative h-9 w-9 rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)] hover:text-[#15803d]" data-tooltip="Aktifkan checksheet">
                                                    <x-ui.icon name="play" class="h-[18px] w-[18px]" />
                                                    <span class="sr-only">Aktifkan checksheet</span>
                                                </button>
                                            @endif
                                            <button type="button" data-checksheet-delete-open data-checksheet-delete-url="{{ route('master-checksheet.destroy', $checksheet->id) }}" class="prime-icon-tooltip relative h-9 w-9 rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)] hover:text-[#dc2626]" data-tooltip="Hapus checksheet">
                                                <x-ui.icon name="trash" class="h-[18px] w-[18px]" />
                                                <span class="sr-only">Hapus checksheet</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-[10px] p-3 lg:hidden" data-checksheet-table-mobile>
                    @foreach ($checksheets as $checksheet)
                        @php
                            $machineCount = $checksheet->machineAssignments->count();
                            $partCount = $checksheet->machineAssignments->sum(fn ($assignment) => $assignment->parts->count());
                            $standardCount = $checksheet->machineAssignments->sum(fn ($assignment) => $assignment->parts->sum(fn ($part) => $part->standards->count()));
                            $firstSchedule = $checksheet->machineAssignments->first()?->schedules->first();
                            $scheduleLabel = $firstSchedule ? ucfirst($firstSchedule->frequency_type) : '-';
                            $firstMachineCode = strtolower((string) ($checksheet->machineAssignments->first()?->machine?->machine_code ?? ''));
                            $firstLocationName = strtolower((string) ($checksheet->machineAssignments->first()?->machine?->location?->location_name ?? ''));
                        @endphp
                        <article class="rounded-[12px] border border-[rgba(0,0,0,0.1)] bg-white p-[14px]" data-checksheet-row data-code="{{ strtolower($checksheet->checksheet_code) }}" data-name="{{ strtolower($checksheet->checksheet_name) }}" data-status="{{ $checksheet->is_active ? 'aktif' : 'nonaktif' }}" data-location="{{ $firstLocationName }}" data-machine="{{ $firstMachineCode }}">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <p class="text-[12px] font-semibold text-[#615d59]">{{ $checksheet->checksheet_code }}</p>
                                <span class="inline-flex min-h-7 items-center rounded-[9999px] px-3 py-1 text-[12px] font-semibold tracking-[0.125px] {{ $checksheet->is_active ? 'bg-[#e9fbf1] text-[#0f8a3b]' : 'bg-[#fff4df] text-[#a15c00]' }}">{{ $checksheet->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                            <h3 class="mb-1 text-[15px] font-bold text-[rgba(0,0,0,0.95)] break-words">{{ $checksheet->checksheet_name }}</h3>
                            <p class="mb-[10px] text-[13px] text-[#615d59]">{{ $machineCount }} Mesin • {{ $partCount }} Part • {{ $standardCount }} Standard • {{ $scheduleLabel }}</p>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('master-checksheet.show', $checksheet->id) }}" class="h-9 rounded-[6px] border border-[rgba(0,0,0,0.1)] px-3 text-[12px] font-semibold">Detail</a>
                                <a href="{{ route('master-checksheet.edit', $checksheet->id) }}" class="h-9 rounded-[6px] border border-[rgba(0,0,0,0.1)] px-3 text-[12px] font-semibold">Edit</a>
                                @if ($checksheet->is_active)
                                    <button type="button" data-checksheet-status-open data-checksheet-status-url="{{ route('master-checksheet.deactivate', $checksheet->id) }}" data-checksheet-status-label="Nonaktifkan Checksheet" data-checksheet-status-description="Checksheet akan dinonaktifkan dan tidak bisa digunakan." data-checksheet-status-button="Nonaktifkan" data-checksheet-status-variant="warning" class="h-9 rounded-[6px] bg-[#b86b00] px-3 text-[12px] font-semibold text-white">Nonaktifkan</button>
                                @else
                                    <button type="button" data-checksheet-status-open data-checksheet-status-url="{{ route('master-checksheet.activate', $checksheet->id) }}" data-checksheet-status-label="Aktifkan Checksheet" data-checksheet-status-description="Checksheet akan diaktifkan kembali dan bisa digunakan." data-checksheet-status-button="Aktifkan" data-checksheet-status-variant="success" class="h-9 rounded-[6px] bg-[#15803d] px-3 text-[12px] font-semibold text-white">Aktifkan</button>
                                @endif
                            </div>
                            <button type="button" data-checksheet-delete-open data-checksheet-delete-url="{{ route('master-checksheet.destroy', $checksheet->id) }}" class="mt-2 h-9 rounded-[6px] border border-[#ef4444] bg-white px-3 text-[12px] font-semibold text-[#dc2626]">Hapus Permanen</button>
                        </article>
                    @endforeach
                </div>

                <div data-checksheet-pagination>
                    <x-ui.numeric-pagination :paginator="$checksheets" summary-class="text-[13px] font-normal text-[#615d59]" container-class="flex flex-col gap-4 border-t border-[rgba(0,0,0,0.1)] px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between" />
                </div>
            </x-ui.card>
        @endif
    </section>

    <x-ui.modal id="checksheet-status-modal" overlayClass="bg-black/35" panelClass="max-w-[560px] rounded-[16px] bg-white p-6 shadow-[rgba(0,0,0,0.01)_0px_1px_3px,rgba(0,0,0,0.02)_0px_3px_7px,rgba(0,0,0,0.02)_0px_7px_15px,rgba(0,0,0,0.04)_0px_14px_28px,rgba(0,0,0,0.05)_0px_23px_52px]">
        <div class="flex items-start gap-4">
            <div data-checksheet-status-icon-wrap class="flex h-12 w-12 items-center justify-center rounded-full bg-[#fff4df] text-[#a15c00]">
                <svg data-checksheet-status-icon class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 2v8"></path>
                    <path d="M7.8 4.8a8 8 0 1 0 8.4 0"></path>
                </svg>
            </div>
            <div class="flex-1">
                <h3 data-checksheet-status-title class="text-[26px] font-semibold tracking-[-0.625px] text-[rgba(0,0,0,0.95)]">Nonaktifkan Checksheet</h3>
                <p data-checksheet-status-modal-description class="mt-3 text-[16px] leading-[1.5] text-[#615d59]">Checksheet akan dinonaktifkan dan tidak bisa digunakan.</p>
            </div>
        </div>
        <div class="mt-6 border-t border-[rgba(0,0,0,0.1)] pt-5">
            <form action="#" method="POST" class="flex justify-end gap-3" data-checksheet-status-form>
                @csrf
                @method('PATCH')
                <x-ui.button variant="secondary" data-modal-close="checksheet-status-modal">Batalkan</x-ui.button>
                <button type="submit" data-checksheet-status-submit class="inline-flex h-11 items-center justify-center rounded-[6px] bg-[#b86b00] px-[18px] text-[14px] font-semibold text-white transition hover:bg-[#965500]">Nonaktifkan</button>
            </form>
        </div>
    </x-ui.modal>

    <x-ui.modal id="checksheet-delete-modal" overlayClass="bg-black/35" panelClass="max-w-[560px] rounded-[16px] bg-white p-6 shadow-[rgba(0,0,0,0.01)_0px_1px_3px,rgba(0,0,0,0.02)_0px_3px_7px,rgba(0,0,0,0.02)_0px_7px_15px,rgba(0,0,0,0.04)_0px_14px_28px,rgba(0,0,0,0.05)_0px_23px_52px]">
        <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[var(--color-prime-danger-soft)] text-[#dc2626]">
                <x-ui.icon name="trash" class="h-6 w-6" />
            </div>
            <div class="flex-1">
                <h3 class="text-[26px] font-semibold tracking-[-0.625px] text-[rgba(0,0,0,0.95)]">Hapus Permanen</h3>
                <p class="mt-3 text-[16px] leading-[1.5] text-[#615d59]">Data checksheet akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.</p>
            </div>
        </div>
        <div class="mt-6 border-t border-[rgba(0,0,0,0.1)] pt-5">
            <form action="#" method="POST" class="flex justify-end gap-3" data-checksheet-delete-form>
                @csrf
                @method('DELETE')
                <x-ui.button variant="secondary" data-modal-close="checksheet-delete-modal">Batalkan</x-ui.button>
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-[6px] bg-[#dc2626] px-[18px] text-[14px] font-semibold text-white transition hover:bg-[#b91c1c]">Hapus Permanen</button>
            </form>
        </div>
    </x-ui.modal>
</x-layouts.app>
