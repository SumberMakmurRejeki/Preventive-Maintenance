<x-layouts.app
    title="Master Mesin PRIME"
    heading="Master Mesin"
    :actor-name="$actorName"
    :role="$role"
>
    @php
        $editingMachineData = $editingMachine;
    @endphp

    <section
        class="mt-8 space-y-6 font-['NotionInter','Inter',ui-sans-serif,system-ui,sans-serif] text-[rgba(0,0,0,0.95)]"
        data-machine-master-page
        data-open-modal="{{ $modalState['action'] ?? '' }}"
        data-machine-store-url="{{ route('master-mesin.store') }}"
    >
        <div class="space-y-6">
            <div class="flex items-center gap-2 text-[15px] font-medium text-[rgba(0,0,0,0.95)]">
                <span>PM Management</span>
                <span class="text-[#a39e98]">/</span>
                <span>Master Mesin</span>
            </div>

            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-[40px] font-bold leading-[1.1] tracking-[-0.8px] text-[rgba(0,0,0,0.95)]">Master Mesin</h2>
                    <p class="mt-2 text-[16px] font-normal leading-[1.5] text-[#615d59]">Kelola data mesin, lokasi, status, dan QR code.</p>
                </div>

                <x-ui.button size="lg" data-machine-create-open class="h-[42px] w-full rounded-[6px] bg-[#0075de] px-[18px] py-0 text-[14px] font-semibold text-white hover:bg-[#005bab] lg:h-11 lg:w-auto" aria-label="Tambah mesin">
                    Tambah Mesin
                </x-ui.button>
            </div>
        </div>

        @if (session('flash_success'))
            <x-ui.alert variant="success" class="rounded-[12px] border border-[#b8efcc] bg-[#e9fbf1] px-4 py-3 text-[14px] text-[#0f8a3b]" data-machine-success-alert>
                {{ session('flash_success') }}
            </x-ui.alert>
        @endif

        @if (session('flash_error'))
            <x-ui.alert variant="error">
                {{ session('flash_error') }}
            </x-ui.alert>
        @endif

        <x-ui.card padding="p-0" class="table-card mx-auto w-full max-w-[1440px] overflow-hidden rounded-[12px] border border-[rgba(0,0,0,0.1)] bg-white shadow-[rgba(0,0,0,0.02)_0px_4px_18px]">
            <div class="table-toolbar border-b border-[rgba(0,0,0,0.1)] px-[14px] py-[14px] sm:px-4 sm:py-4" data-machine-filter-wrap>
                <form class="flex flex-col items-stretch gap-[10px] md:items-center md:gap-3 lg:flex-row lg:items-center lg:gap-3" data-machine-filter-form>
                    <div class="search-control relative block min-w-0 lg:w-[320px]" aria-label="Cari mesin">
                        <label class="relative block">
                            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-[#615d59]">
                                <x-ui.icon name="magnifying-glass" class="h-[18px] w-[18px]" />
                            </span>
                            <input
                                type="search"
                                id="searchMachine"
                                value="{{ $filters['search'] }}"
                                placeholder="Cari kode atau nama mesin..."
                                class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white py-0 pl-11 pr-9 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition placeholder:text-[#615d59] focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10"
                                data-machine-filter-search
                            >
                            <span class="pointer-events-none absolute inset-y-0 right-3 hidden items-center text-[#615d59]" data-machine-filter-search-loading>
                                <span class="h-4 w-4 animate-spin rounded-full border-2 border-[#0075de]/30 border-t-[#0075de]"></span>
                            </span>
                        </label>
                    </div>

                    <label class="filter-control block lg:w-40" aria-label="Filter lokasi">
                            <select
                                id="filterLocation"
                                class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10"
                                data-machine-filter-select
                            >
                                <option value="">Semua Lokasi</option>
                                @foreach ($filterLocations as $location)
                                    <option value="{{ strtolower($location->location_name) }}" @selected((int) ($filters['location_id'] ?? 0) === $location->id)>
                                        {{ $location->location_name }}
                                    </option>
                                @endforeach
                            </select>
                    </label>

                    <label class="filter-control block lg:w-40" aria-label="Filter status">
                            <select
                                id="filterStatus"
                                class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10"
                                data-machine-filter-select
                            >
                                <option value="">Semua Status</option>
                                <option value="aktif" @selected($filters['status'] === 'active')>Aktif</option>
                                <option value="nonaktif" @selected($filters['status'] === 'inactive')>Nonaktif</option>
                            </select>
                    </label>

                    @php
                        $hasFilter = ($filters['search'] ?? '') !== '' || ($filters['status'] ?? '') !== '' || filled($filters['location_id'] ?? null);
                    @endphp
                    <div class="reset-filter flex items-center justify-end lg:ml-auto">
                        <button
                            type="button"
                            @class([
                                'inline-flex h-10 w-full items-center justify-center rounded-[6px] border border-[rgba(0,0,0,0.1)] px-4 text-[14px] font-medium transition lg:w-auto',
                                'pointer-events-none opacity-50' => ! $hasFilter,
                                'text-[rgba(0,0,0,0.95)] hover:bg-[rgba(0,0,0,0.03)]' => $hasFilter,
                            ])
                            id="resetFilter"
                            data-machine-filter-reset
                        >
                            Reset Filter
                        </button>
                    </div>
                </form>
            </div>

            @if ($machines->count() === 0)
                <div class="px-6 py-10">
                    @if (($filters['search'] ?? '') !== '' || ($filters['status'] ?? '') !== '' || filled($filters['location_id'] ?? null))
                        <x-ui.empty-state
                            title="Mesin yang kamu cari tidak ditemukan"
                            message="Coba ubah kata kunci atau reset filter."
                        />
                    @else
                        <x-ui.empty-state
                            title="Belum ada data mesin."
                            message="Tambahkan mesin untuk mulai menggunakan PM Management."
                        />
                    @endif
                </div>
            @else
                <div class="hidden px-6 py-10" data-machine-empty-filter>
                    <x-ui.empty-state
                        title="Mesin yang kamu cari tidak ditemukan"
                        message="Coba ubah kata kunci atau reset filter."
                    />
                </div>

                <div class="desktop-table hidden overflow-x-auto lg:block" data-machine-table-desktop>
                    <table class="machine-table min-w-full table-fixed border-separate border-spacing-0 [&_th]:h-14 [&_th]:whitespace-nowrap [&_th]:px-4 [&_td]:h-14 [&_td]:whitespace-nowrap [&_td]:px-4 [&_td]:align-middle">
                        <colgroup>
                            <col style="width: 56px">
                            <col style="width: 140px">
                            <col style="width: 280px">
                            <col style="width: 180px">
                            <col style="width: 120px">
                            <col style="width: 80px">
                            <col style="width: 160px">
                        </colgroup>
                        <thead class="bg-[#f6f5f4]">
                            <tr class="h-12 border-b border-[rgba(0,0,0,0.1)]">
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">No</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-[rgba(0,0,0,0.95)]" data-sort-trigger data-sort-group="machine" data-sort-field="code" aria-label="Urutkan Kode Mesin">
                                        Kode Mesin
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="machine" data-sort-field="code">↕</span>
                                    </button>
                                </th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-[rgba(0,0,0,0.95)]" data-sort-trigger data-sort-group="machine" data-sort-field="name" aria-label="Urutkan Nama Mesin">
                                        Nama Mesin
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="machine" data-sort-field="name">↕</span>
                                    </button>
                                </th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Lokasi</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Status</th>
                                <th class="text-center text-[13px] font-semibold text-[#615d59]">QR</th>
                                <th class="text-center text-[13px] font-semibold text-[#615d59]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            @foreach ($machines as $machine)
                                @php
                                    $qrImageUrl = $machine->qr_code_path ? \Illuminate\Support\Facades\Storage::url($machine->qr_code_path) : null;
                                @endphp
                                <tr
                                    class="h-14 border-b border-[rgba(0,0,0,0.08)] text-[14px] text-[rgba(0,0,0,0.95)] transition hover:bg-[#f6f5f4]"
                                    data-machine-row
                                    data-code="{{ strtolower($machine->machine_code) }}"
                                    data-name="{{ strtolower($machine->machine_name) }}"
                                    data-location="{{ strtolower($machine->location?->location_name ?? 'lokasi terhapus') }}"
                                    data-status="{{ $machine->is_active ? 'aktif' : 'nonaktif' }}"
                                >
                                    <td class="px-4">{{ $machines->firstItem() + $loop->index }}</td>
                                    <td class="px-4">{{ $machine->machine_code }}</td>
                                    <td class="px-4">{{ $machine->machine_name }}</td>
                                    <td class="px-4">{{ $machine->location?->location_name ?? 'Lokasi terhapus' }}</td>
                                    <td class="px-4">
                                        <span class="inline-flex min-h-7 items-center rounded-[9999px] px-3 py-1 text-[12px] font-semibold tracking-[0.125px] {{ $machine->is_active ? 'bg-[#e9fbf1] text-[#0f8a3b]' : 'bg-[#fff4df] text-[#a15c00]' }}">
                                            {{ $machine->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="px-4 text-center">
                                        <button type="button" data-machine-qr-open data-machine-id="{{ $machine->id }}" data-machine-code="{{ $machine->machine_code }}" data-machine-name="{{ $machine->machine_name }}" data-machine-location="{{ $machine->location?->location_name ?? 'Lokasi terhapus' }}" data-machine-qr-image="{{ $qrImageUrl }}" data-machine-qr-generate-url="{{ route('master-mesin.generate-qr', $machine->id) }}" class="h-9 w-9 rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)] hover:text-[#0075de]" aria-label="Lihat / cetak QR" title="Lihat / cetak QR"><x-ui.icon name="qr-code" class="h-[18px] w-[18px]" /></button>
                                    </td>
                                    <td class="px-4">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button" data-machine-detail-open data-machine-code="{{ $machine->machine_code }}" data-machine-name="{{ $machine->machine_name }}" data-machine-location="{{ $machine->location?->location_name ?? 'Lokasi terhapus' }}" data-machine-status="{{ $machine->is_active ? 'Aktif' : 'Nonaktif' }}" data-machine-description="{{ $machine->description ?: 'Belum ada deskripsi.' }}" data-machine-created-at="{{ optional($machine->created_at)->translatedFormat('d F Y H:i') }}" data-machine-updated-at="{{ optional($machine->updated_at)->translatedFormat('d F Y H:i') }}" class="h-9 w-9 rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)]" aria-label="Lihat detail" title="Lihat detail"><x-ui.icon name="eye" class="h-[18px] w-[18px]" /></button>
                                            <button type="button" data-machine-edit-open data-machine-id="{{ $machine->id }}" data-machine-update-url="{{ route('master-mesin.update', $machine->id) }}" data-machine-code="{{ $machine->machine_code }}" data-machine-name="{{ $machine->machine_name }}" data-machine-location-id="{{ $machine->location_id }}" data-machine-active="{{ $machine->is_active ? 1 : 0 }}" data-machine-description="{{ $machine->description }}" class="h-9 w-9 rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)] hover:text-[#0075de]" aria-label="Edit mesin" title="Edit mesin"><x-ui.icon name="pencil-square" class="h-[18px] w-[18px]" /></button>
                                            @if ($machine->is_active)
                                                <button type="button" data-machine-status-open data-machine-status-url="{{ route('master-mesin.deactivate', $machine->id) }}" data-machine-status-label="Nonaktifkan Mesin" data-machine-status-description="Mesin akan dinonaktifkan dan tidak bisa digunakan." data-machine-status-button="Nonaktifkan" data-machine-status-variant="warning" class="h-9 w-9 rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)] hover:text-[#b86b00]" aria-label="Nonaktifkan mesin" title="Nonaktifkan mesin"><x-ui.icon name="power" class="h-[18px] w-[18px]" /></button>
                                            @else
                                                <button type="button" data-machine-status-open data-machine-status-url="{{ route('master-mesin.activate', $machine->id) }}" data-machine-status-label="Aktifkan Mesin" data-machine-status-description="Mesin akan diaktifkan dan bisa digunakan." data-machine-status-button="Aktifkan" data-machine-status-variant="success" class="h-9 w-9 rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)] hover:text-[#15803d]" aria-label="Aktifkan mesin" title="Aktifkan mesin"><x-ui.icon name="play" class="h-[18px] w-[18px]" /></button>
                                            @endif
                                            <button type="button" data-machine-delete-open data-machine-delete-url="{{ route('master-mesin.destroy', $machine->id) }}" class="h-9 w-9 rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)] hover:text-[#dc2626]" aria-label="Hapus permanen" title="Hapus permanen"><x-ui.icon name="trash" class="h-[18px] w-[18px]" /></button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mobile-list grid gap-[10px] p-3 lg:hidden" data-machine-table-mobile>
                    @foreach ($machines as $machine)
                        @php
                            $qrImageUrl = $machine->qr_code_path ? \Illuminate\Support\Facades\Storage::url($machine->qr_code_path) : null;
                        @endphp
                        <article
                            class="machine-card relative h-auto min-h-0 rounded-[12px] border border-[rgba(0,0,0,0.1)] bg-white p-[14px]"
                            data-machine-row
                            data-code="{{ strtolower($machine->machine_code) }}"
                            data-name="{{ strtolower($machine->machine_name) }}"
                            data-location="{{ strtolower($machine->location?->location_name ?? 'lokasi terhapus') }}"
                            data-status="{{ $machine->is_active ? 'aktif' : 'nonaktif' }}"
                        >
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <p class="text-[12px] font-semibold text-[#615d59]">{{ $machine->machine_code }}</p>
                                <span class="inline-flex min-h-7 items-center rounded-[9999px] px-3 py-1 text-[12px] font-semibold tracking-[0.125px] {{ $machine->is_active ? 'bg-[#e9fbf1] text-[#0f8a3b]' : 'bg-[#fff4df] text-[#a15c00]' }}">{{ $machine->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                            <h3 class="mb-1 text-[15px] font-bold text-[rgba(0,0,0,0.95)]">{{ $machine->machine_name }}</h3>
                            <p class="mb-[10px] text-[13px] text-[#615d59]">Lokasi: {{ $machine->location?->location_name ?? 'Lokasi terhapus' }}</p>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" data-machine-qr-open data-machine-id="{{ $machine->id }}" data-machine-code="{{ $machine->machine_code }}" data-machine-name="{{ $machine->machine_name }}" data-machine-location="{{ $machine->location?->location_name ?? 'Lokasi terhapus' }}" data-machine-qr-image="{{ $qrImageUrl }}" data-machine-qr-generate-url="{{ route('master-mesin.generate-qr', $machine->id) }}" class="h-9 rounded-[6px] border border-[rgba(0,0,0,0.1)] px-3 text-[12px] font-semibold" aria-label="Lihat / cetak QR">QR</button>
                                <button type="button" data-machine-detail-open data-machine-code="{{ $machine->machine_code }}" data-machine-name="{{ $machine->machine_name }}" data-machine-location="{{ $machine->location?->location_name ?? 'Lokasi terhapus' }}" data-machine-status="{{ $machine->is_active ? 'Aktif' : 'Nonaktif' }}" data-machine-description="{{ $machine->description ?: 'Belum ada deskripsi.' }}" data-machine-created-at="{{ optional($machine->created_at)->translatedFormat('d F Y H:i') }}" data-machine-updated-at="{{ optional($machine->updated_at)->translatedFormat('d F Y H:i') }}" class="h-9 rounded-[6px] border border-[rgba(0,0,0,0.1)] px-3 text-[12px] font-semibold" aria-label="Lihat detail">Detail</button>
                                <button type="button" data-machine-edit-open data-machine-id="{{ $machine->id }}" data-machine-update-url="{{ route('master-mesin.update', $machine->id) }}" data-machine-code="{{ $machine->machine_code }}" data-machine-name="{{ $machine->machine_name }}" data-machine-location-id="{{ $machine->location_id }}" data-machine-active="{{ $machine->is_active ? 1 : 0 }}" data-machine-description="{{ $machine->description }}" class="h-9 rounded-[6px] border border-[rgba(0,0,0,0.1)] px-3 text-[12px] font-semibold" aria-label="Edit mesin">Edit</button>
                                @if ($machine->is_active)
                                    <button type="button" data-machine-status-open data-machine-status-url="{{ route('master-mesin.deactivate', $machine->id) }}" data-machine-status-label="Nonaktifkan Mesin" data-machine-status-description="Mesin akan dinonaktifkan dan tidak bisa digunakan." data-machine-status-button="Nonaktifkan" data-machine-status-variant="warning" class="h-9 rounded-[6px] bg-[#b86b00] px-3 text-[12px] font-semibold text-white" aria-label="Nonaktifkan mesin">Nonaktifkan</button>
                                @else
                                    <button type="button" data-machine-status-open data-machine-status-url="{{ route('master-mesin.activate', $machine->id) }}" data-machine-status-label="Aktifkan Mesin" data-machine-status-description="Mesin akan diaktifkan dan bisa digunakan." data-machine-status-button="Aktifkan" data-machine-status-variant="success" class="h-9 rounded-[6px] bg-[#15803d] px-3 text-[12px] font-semibold text-white" aria-label="Aktifkan mesin">Aktifkan</button>
                                @endif
                            </div>
                            <button type="button" data-machine-delete-open data-machine-delete-url="{{ route('master-mesin.destroy', $machine->id) }}" class="mt-2 h-9 rounded-[6px] border border-[#ef4444] bg-white px-3 text-[12px] font-semibold text-[#dc2626]" aria-label="Hapus permanen">Hapus Permanen</button>
                        </article>
                    @endforeach
                </div>

                <div data-machine-pagination>
                    <x-ui.numeric-pagination
                        :paginator="$machines"
                        summary-class="text-[13px] font-normal text-[#615d59]"
                        container-class="flex flex-col gap-4 border-t border-[rgba(0,0,0,0.1)] px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between"
                    />
                </div>
            @endif
        </x-ui.card>
    </section>

    <x-ui.modal id="machine-form-modal" :title="($modalState['action'] ?? null) === 'edit' ? 'Edit Mesin' : 'Tambah Mesin'" overlayClass="bg-black/35" panelClass="my-4 max-h-[calc(100vh-2rem)] max-w-[560px] overflow-y-auto rounded-[16px] bg-white p-6 shadow-[rgba(0,0,0,0.01)_0px_1px_3px,rgba(0,0,0,0.02)_0px_3px_7px,rgba(0,0,0,0.02)_0px_7px_15px,rgba(0,0,0,0.04)_0px_14px_28px,rgba(0,0,0,0.05)_0px_23px_52px]">
        <form action="{{ ($modalState['action'] ?? null) === 'edit' && $editingMachineData ? route('master-mesin.update', $editingMachineData->id) : route('master-mesin.store') }}" method="POST" class="space-y-5" data-machine-form>
            @csrf
            <div data-machine-form-method-wrapper>
                @if (($modalState['action'] ?? null) === 'edit')
                    @method('PUT')
                @endif
            </div>
            <input type="hidden" name="modal_action" value="{{ ($modalState['action'] ?? null) === 'edit' ? 'edit' : 'create' }}" data-machine-form-action-input>
            <input type="hidden" name="machine_id" value="{{ old('machine_id', $editingMachineData?->id) }}" data-machine-form-id-input>

            <div>
                <label for="machine_code" class="mb-2 block text-[14px] font-semibold text-[rgba(0,0,0,0.95)]">Kode Mesin <span class="text-[var(--color-prime-danger)]">*</span></label>
                <input id="machine_code" type="text" name="machine_code" value="{{ old('machine_code', $editingMachineData?->machine_code) }}" placeholder="Contoh: M-001" data-machine-form-field="code" @readonly(($modalState['action'] ?? null) === 'edit') class="h-11 w-full rounded-[6px] border border-[#dddddd] px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition placeholder:text-[#615d59] focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10 {{ ($modalState['action'] ?? null) === 'edit' ? 'bg-[var(--color-prime-soft)]' : '' }}">
                @error('machine_code')<p class="mt-2 text-sm text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="machine_name" class="mb-2 block text-[14px] font-semibold text-[rgba(0,0,0,0.95)]">Nama Mesin <span class="text-[var(--color-prime-danger)]">*</span></label>
                <input id="machine_name" type="text" name="machine_name" value="{{ old('machine_name', $editingMachineData?->machine_name) }}" data-machine-form-field="name" class="h-11 w-full rounded-[6px] border border-[#dddddd] px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10">
                @error('machine_name')<p class="mt-2 text-sm text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="location_id" class="mb-2 block text-[14px] font-semibold text-[rgba(0,0,0,0.95)]">Lokasi <span class="text-[var(--color-prime-danger)]">*</span></label>
                <select id="location_id" name="location_id" data-machine-form-field="location" class="h-11 w-full rounded-[6px] border border-[#dddddd] px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10">
                    <option value="">Pilih Lokasi</option>
                    @foreach ($formLocations as $location)
                        <option value="{{ $location->id }}" @selected((int) old('location_id', $editingMachineData?->location_id) === $location->id)>{{ $location->location_name }}@if (! $location->is_active) (Nonaktif) @endif</option>
                    @endforeach
                </select>
                @error('location_id')<p class="mt-2 text-sm text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="machine_is_active" class="mb-2 block text-[14px] font-semibold text-[rgba(0,0,0,0.95)]">Status</label>
                <select id="machine_is_active" name="is_active" data-machine-form-field="active" class="h-11 w-full rounded-[6px] border border-[#dddddd] px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10">
                    <option value="1" @selected((string) old('is_active', $editingMachineData?->is_active ? '1' : '0') === '1')>Aktif</option>
                    <option value="0" @selected((string) old('is_active', $editingMachineData?->is_active ? '1' : '0') === '0')>Nonaktif</option>
                </select>
            </div>

            <div>
                <label for="machine_description" class="mb-2 block text-[14px] font-semibold text-[rgba(0,0,0,0.95)]">Deskripsi</label>
                <textarea id="machine_description" name="description" rows="4" data-machine-form-field="description" class="h-28 w-full rounded-[6px] border border-[#dddddd] px-[14px] py-3 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10">{{ old('description', $editingMachineData?->description) }}</textarea>
            </div>

            <div class="flex justify-end gap-3 border-t border-[rgba(0,0,0,0.1)] pt-4">
                <x-ui.button variant="secondary" data-modal-close="machine-form-modal">Batal</x-ui.button>
                <x-ui.button type="submit" class="h-11 rounded-[6px] bg-[#0075de] px-[18px] text-[14px] font-semibold text-white hover:bg-[#005bab]" data-machine-form-submit-label>Simpan</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="machine-qr-modal" overlayClass="bg-black/35" panelClass="max-w-[560px] rounded-[16px] bg-white p-6 shadow-[rgba(0,0,0,0.01)_0px_1px_3px,rgba(0,0,0,0.02)_0px_3px_7px,rgba(0,0,0,0.02)_0px_7px_15px,rgba(0,0,0,0.04)_0px_14px_28px,rgba(0,0,0,0.05)_0px_23px_52px]">
        <div class="text-center">
            <h3 class="text-[26px] font-bold tracking-[-0.625px] text-[rgba(0,0,0,0.95)]">QR Code Mesin</h3>
            <p data-machine-qr-code class="mt-2 text-[16px] font-semibold">MSN-000</p>
            <p data-machine-qr-name class="mt-1 text-[14px] text-[#615d59]">Nama Mesin</p>
            <p data-machine-qr-location class="mt-1 text-[14px] text-[#615d59]">Lokasi Mesin</p>
            <div class="mx-auto mt-6 flex w-fit items-center justify-center rounded-[12px] border border-[rgba(0,0,0,0.1)] bg-white p-4"><img src="" alt="QR Code Mesin" data-machine-qr-image-preview class="h-44 w-44 object-contain"></div>
            <div class="mt-6 flex flex-wrap justify-center gap-3 border-t border-[rgba(0,0,0,0.1)] pt-5">
                <x-ui.button variant="secondary" data-modal-close="machine-qr-modal">Tutup</x-ui.button>
                <form action="#" method="POST" data-machine-qr-generate-form>@csrf<x-ui.button type="submit" variant="secondary">Generate Ulang</x-ui.button></form>
                <a href="javascript:void(0)" role="button" data-machine-qr-download-link class="inline-flex h-11 items-center justify-center gap-2 rounded-[6px] bg-[#0075de] px-[18px] text-[14px] font-semibold text-white transition hover:bg-[#005bab]"><x-ui.icon name="arrow-down-tray" class="h-4 w-4" />Download PNG</a>
            </div>
        </div>
    </x-ui.modal>

    <x-ui.modal id="machine-detail-modal" title="Detail Mesin" overlayClass="bg-black/35" panelClass="max-w-[560px] rounded-[16px] bg-white p-6 shadow-[rgba(0,0,0,0.01)_0px_1px_3px,rgba(0,0,0,0.02)_0px_3px_7px,rgba(0,0,0,0.02)_0px_7px_15px,rgba(0,0,0,0.04)_0px_14px_28px,rgba(0,0,0,0.05)_0px_23px_52px]">
        <div class="space-y-5">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-[14px] font-semibold text-[#615d59]">Kode Mesin</dt><dd data-machine-detail-code class="mt-1 text-[14px] font-medium">-</dd></div>
                <div><dt class="text-[14px] font-semibold text-[#615d59]">Status</dt><dd data-machine-detail-status class="mt-1 text-[14px]">-</dd></div>
                <div><dt class="text-[14px] font-semibold text-[#615d59]">Nama Mesin</dt><dd data-machine-detail-name class="mt-1 text-[14px]">-</dd></div>
                <div><dt class="text-[14px] font-semibold text-[#615d59]">Lokasi</dt><dd data-machine-detail-location class="mt-1 text-[14px]">-</dd></div>
                <div class="sm:col-span-2"><dt class="text-[14px] font-semibold text-[#615d59]">Deskripsi</dt><dd data-machine-detail-description class="mt-1 text-[14px] leading-[1.5]">Belum ada deskripsi.</dd></div>
                <div><dt class="text-[14px] font-semibold text-[#615d59]">Tanggal dibuat</dt><dd data-machine-detail-created-at class="mt-1 text-[14px]">-</dd></div>
                <div><dt class="text-[14px] font-semibold text-[#615d59]">Terakhir diperbarui</dt><dd data-machine-detail-updated-at class="mt-1 text-[14px]">-</dd></div>
            </dl>
            <div class="flex justify-end border-t border-[rgba(0,0,0,0.1)] pt-4"><x-ui.button variant="secondary" data-modal-close="machine-detail-modal">Tutup</x-ui.button></div>
        </div>
    </x-ui.modal>

    <x-ui.modal id="machine-status-modal" overlayClass="bg-black/35" panelClass="max-w-[560px] rounded-[16px] bg-white p-6 shadow-[rgba(0,0,0,0.01)_0px_1px_3px,rgba(0,0,0,0.02)_0px_3px_7px,rgba(0,0,0,0.02)_0px_7px_15px,rgba(0,0,0,0.04)_0px_14px_28px,rgba(0,0,0,0.05)_0px_23px_52px]">
        <div class="flex items-start gap-4">
            <div data-machine-status-icon-wrap class="flex h-12 w-12 items-center justify-center rounded-full bg-[#fff4df] text-[#a15c00]"><svg data-machine-status-icon class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v8"></path><path d="M7.8 4.8a8 8 0 1 0 8.4 0"></path></svg></div>
            <div class="flex-1">
                <h3 data-machine-status-title class="text-[26px] font-semibold tracking-[-0.625px]">Nonaktifkan Mesin</h3>
                <p data-machine-status-description-text class="mt-3 text-[16px] leading-[1.5] text-[#615d59]">Mesin akan dinonaktifkan dan tidak bisa digunakan.</p>
            </div>
        </div>
        <div class="mt-6 border-t border-[rgba(0,0,0,0.1)] pt-5">
            <form action="#" method="POST" class="flex justify-end gap-3" data-machine-status-form>
                @csrf
                <div data-machine-status-method-wrapper>@method('PATCH')</div>
                <x-ui.button variant="secondary" data-modal-close="machine-status-modal">Batalkan</x-ui.button>
                <button type="submit" data-machine-status-submit class="inline-flex h-11 items-center justify-center rounded-[6px] bg-[#b86b00] px-[18px] text-[14px] font-semibold text-white transition hover:bg-[#965500]">Nonaktifkan</button>
            </form>
        </div>
    </x-ui.modal>

    <x-ui.modal id="machine-delete-modal" overlayClass="bg-black/35" panelClass="max-w-[560px] rounded-[16px] bg-white p-6 shadow-[rgba(0,0,0,0.01)_0px_1px_3px,rgba(0,0,0,0.02)_0px_3px_7px,rgba(0,0,0,0.02)_0px_7px_15px,rgba(0,0,0,0.04)_0px_14px_28px,rgba(0,0,0,0.05)_0px_23px_52px]">
        <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[var(--color-prime-danger-soft)] text-[#dc2626]"><x-ui.icon name="trash" class="h-6 w-6" /></div>
            <div class="flex-1">
                <h3 class="text-[26px] font-semibold tracking-[-0.625px]">Hapus Permanen</h3>
                <p class="mt-3 text-[16px] leading-[1.5] text-[#615d59]">Data mesin akan dihapus permanen. History PM, breakdown, dan data terkait mesin ini dapat ikut terhapus.</p>
            </div>
        </div>
        <div class="mt-6 border-t border-[rgba(0,0,0,0.1)] pt-5">
            <form action="#" method="POST" class="flex justify-end gap-3" data-machine-delete-form>
                @csrf
                @method('DELETE')
                <x-ui.button variant="secondary" data-modal-close="machine-delete-modal">Batalkan</x-ui.button>
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-[6px] bg-[#dc2626] px-[18px] text-[14px] font-semibold text-white transition hover:bg-[#b91c1c]">Hapus Permanen</button>
            </form>
        </div>
    </x-ui.modal>
</x-layouts.app>
