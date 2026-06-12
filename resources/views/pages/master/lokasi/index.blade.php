<x-layouts.app
    title="Master Lokasi PRIME"
    heading="Master Lokasi"
    :actor-name="$actorName"
    :role="$role"
>
    @php
        $editingLocationData = $editingLocation;
    @endphp

    <section class="mt-8 space-y-6 font-['NotionInter','Inter',ui-sans-serif,system-ui,sans-serif] text-[rgba(0,0,0,0.95)]" data-location-page data-open-modal="{{ $modalState['action'] ?? '' }}">
        <div class="space-y-6">
            <div class="flex items-center gap-2 text-[15px] font-medium text-[rgba(0,0,0,0.95)]">
                <span>PM Management</span>
                <span class="text-[#a39e98]">/</span>
                <span>Master Lokasi</span>
            </div>

            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-[40px] font-bold leading-[1.1] tracking-[-0.8px] text-[rgba(0,0,0,0.95)]">Master Lokasi</h2>
                    <p class="mt-2 text-[16px] font-normal leading-[1.5] text-[#615d59]">Kelola data lokasi penempatan mesin produksi.</p>
                </div>

                <x-ui.button size="lg" data-location-create-open class="h-[42px] w-full rounded-[6px] bg-[#0075de] px-[18px] py-0 text-[14px] font-semibold text-white hover:bg-[#005bab] lg:h-11 lg:w-auto">
                    Tambah Lokasi
                </x-ui.button>
            </div>
        </div>

        @if (session('flash_success'))
            <x-ui.alert variant="success">
                {{ session('flash_success') }}
            </x-ui.alert>
        @endif

        @if (session('flash_error'))
            <x-ui.alert variant="error">
                {{ session('flash_error') }}
            </x-ui.alert>
        @endif

        <x-ui.card padding="p-0" class="table-card mx-auto w-full max-w-[1440px] overflow-hidden rounded-[12px] border border-[rgba(0,0,0,0.1)] bg-white shadow-[rgba(0,0,0,0.02)_0px_4px_18px]">
            <div class="table-toolbar border-b border-[rgba(0,0,0,0.1)] px-4 py-4" data-location-filter-wrap>
                <form class="flex flex-col items-stretch gap-[10px] md:items-center md:gap-3 lg:flex-row lg:items-center lg:gap-3" data-location-filter-form>
                    <label class="relative block md:w-[320px]">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-[#615d59]">
                            <x-ui.icon name="magnifying-glass" class="h-[18px] w-[18px]" />
                        </span>
                        <input
                            type="search"
                            id="locationSearch"
                            value="{{ $filters['search'] }}"
                            placeholder="Cari kode atau nama lokasi..."
                            class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white py-0 pl-11 pr-9 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition placeholder:text-[#615d59] focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10"
                            data-location-filter-search
                        >
                        <span class="pointer-events-none absolute inset-y-0 right-3 hidden items-center text-[#615d59]" data-location-filter-search-loading>
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-[#0075de]/30 border-t-[#0075de]"></span>
                        </span>
                    </label>

                    <label class="block md:w-[160px]">
                        <select
                            id="locationStatus"
                            class="h-11 w-full rounded-[6px] border border-[rgba(0,0,0,0.1)] bg-white px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10"
                            data-location-filter-status
                        >
                            <option value="">Semua Status</option>
                            <option value="aktif" @selected($filters['status'] === 'active')>Aktif</option>
                            <option value="nonaktif" @selected($filters['status'] === 'inactive')>Nonaktif</option>
                        </select>
                    </label>
                    <button
                        type="button"
                        class="ml-0 inline-flex h-10 items-center justify-center rounded-[6px] border border-[rgba(0,0,0,0.1)] px-4 text-[14px] font-medium text-[rgba(0,0,0,0.95)] transition hover:bg-[rgba(0,0,0,0.03)] md:ml-auto"
                        id="locationReset"
                        data-location-filter-reset
                    >
                        Reset Filter
                    </button>
                </form>
            </div>

            @if ($locations->count() === 0)
                <div class="px-6 py-8">
                    <x-ui.empty-state
                        title="Belum ada lokasi tersimpan"
                        message="Tambahkan lokasi pertama untuk mulai memetakan penempatan mesin produksi di PRIME."
                    />
                </div>
            @else
                <div class="hidden px-6 py-10" data-location-empty-filter>
                    <x-ui.empty-state
                        title="Lokasi yang kamu cari tidak ditemukan"
                        message="Coba ubah kata kunci atau reset filter."
                    />
                </div>

                <div class="desktop-table hidden overflow-x-auto lg:block" data-location-table-desktop>
                    <table class="min-w-full table-fixed border-separate border-spacing-0 [&_th]:h-14 [&_th]:whitespace-nowrap [&_th]:px-4 [&_td]:h-14 [&_td]:whitespace-nowrap [&_td]:px-4 [&_td]:align-middle">
                        <colgroup>
                            <col style="width: 56px">
                            <col style="width: 140px">
                            <col style="width: 220px">
                            <col style="width: 280px">
                            <col style="width: 120px">
                            <col style="width: 120px">
                            <col style="width: 160px">
                        </colgroup>
                        <thead class="bg-[#f6f5f4]">
                            <tr>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">No</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-[rgba(0,0,0,0.95)]" data-sort-trigger data-sort-group="location" data-sort-field="code" aria-label="Urutkan Kode Lokasi">
                                        Kode Lokasi
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="location" data-sort-field="code">↕</span>
                                    </button>
                                </th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-[rgba(0,0,0,0.95)]" data-sort-trigger data-sort-group="location" data-sort-field="name" aria-label="Urutkan Nama Lokasi">
                                        Nama Lokasi
                                        <span class="text-[11px] leading-none text-[#a39e98]" data-sort-icon data-sort-group="location" data-sort-field="name">↕</span>
                                    </button>
                                </th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Deskripsi</th>
                                <th class="text-center text-[13px] font-semibold text-[#615d59]">Jumlah Mesin</th>
                                <th class="text-left text-[13px] font-semibold text-[#615d59]">Status</th>
                                <th class="text-center text-[13px] font-semibold text-[#615d59]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            @foreach ($locations as $location)
                                <tr
                                    class="border-b border-[rgba(0,0,0,0.08)] text-[14px] text-[rgba(0,0,0,0.95)] transition hover:bg-[#f6f5f4]"
                                    data-location-row
                                    data-code="{{ strtolower($location->location_code) }}"
                                    data-name="{{ strtolower($location->location_name) }}"
                                    data-status="{{ $location->is_active ? 'aktif' : 'nonaktif' }}"
                                >
                                    <td>{{ $locations->firstItem() + $loop->index }}</td>
                                    <td>{{ $location->location_code }}</td>
                                    <td>{{ $location->location_name }}</td>
                                    <td class="text-[#615d59]">{{ $location->description ? \Illuminate\Support\Str::limit($location->description, 42) : 'Belum ada deskripsi' }}</td>
                                    <td class="text-center">{{ $location->machines_count }}</td>
                                    <td>
                                        <span class="inline-flex min-h-7 items-center rounded-[9999px] px-3 py-1 text-[12px] font-semibold tracking-[0.125px] {{ $location->is_active ? 'bg-[#e9fbf1] text-[#0f8a3b]' : 'bg-[#fff4df] text-[#a15c00]' }}">
                                            {{ $location->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-center gap-2">
                                            <button
                                                type="button"
                                                data-location-edit-open
                                                data-location-id="{{ $location->id }}"
                                                data-update-url="{{ route('master-lokasi.update', $location) }}"
                                                data-location-code="{{ $location->location_code }}"
                                                data-location-name="{{ $location->location_name }}"
                                                data-location-description="{{ $location->description }}"
                                                data-location-active="{{ $location->is_active ? 1 : 0 }}"
                                                class="h-9 w-9 rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)] hover:text-[#0075de]"
                                                aria-label="Edit lokasi {{ $location->location_name }}"
                                                title="Edit lokasi"
                                            >
                                                <x-ui.icon name="pencil-square" class="h-5 w-5" />
                                            </button>

                                            @if ($location->is_active)
                                                <button
                                                    type="button"
                                                    data-location-status-open
                                                    data-status-url="{{ route('master-lokasi.deactivate', $location) }}"
                                                    data-status-label="Nonaktifkan Lokasi"
                                                    data-status-description="Lokasi akan dinonaktifkan dan tidak bisa dipilih untuk mesin baru."
                                                    data-status-button="Nonaktifkan"
                                                    data-status-variant="warning"
                                                    class="h-9 w-9 rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)] hover:text-[#b86b00]"
                                                    aria-label="Nonaktifkan lokasi {{ $location->location_name }}"
                                                    title="Nonaktifkan lokasi"
                                                >
                                                    <x-ui.icon name="power" class="h-5 w-5" />
                                                </button>
                                            @else
                                                <button
                                                    type="button"
                                                    data-location-status-open
                                                    data-status-url="{{ route('master-lokasi.activate', $location) }}"
                                                    data-status-label="Aktifkan Lokasi"
                                                    data-status-description="Lokasi akan diaktifkan kembali dan dapat dipilih untuk registrasi mesin baru."
                                                    data-status-button="Aktifkan"
                                                    data-status-variant="success"
                                                    class="h-9 w-9 rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)] hover:text-[#15803d]"
                                                    aria-label="Aktifkan lokasi {{ $location->location_name }}"
                                                    title="Aktifkan lokasi"
                                                >
                                                    <x-ui.icon name="play" class="h-5 w-5" />
                                                </button>
                                            @endif

                                            <button
                                                type="button"
                                                data-location-delete-open
                                                data-delete-url="{{ route('master-lokasi.destroy', $location) }}"
                                                data-active-machines-count="{{ $location->active_machines_count }}"
                                                data-total-machines-count="{{ $location->machines_count }}"
                                                class="h-9 w-9 rounded-[6px] p-2 text-[#615d59] transition hover:bg-[rgba(0,0,0,0.05)] hover:text-[#dc2626]"
                                                aria-label="Hapus lokasi {{ $location->location_name }}"
                                                title="Hapus lokasi"
                                            >
                                                <x-ui.icon name="trash" class="h-5 w-5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-[10px] p-3 lg:hidden" data-location-table-mobile>
                    @foreach ($locations as $location)
                        <article
                            class="rounded-[12px] border border-[rgba(0,0,0,0.1)] bg-white p-[14px]"
                            data-location-row
                            data-code="{{ strtolower($location->location_code) }}"
                            data-name="{{ strtolower($location->location_name) }}"
                            data-status="{{ $location->is_active ? 'aktif' : 'nonaktif' }}"
                        >
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <p class="text-[12px] font-semibold text-[#615d59]">{{ $location->location_code }}</p>
                                <span class="inline-flex min-h-7 items-center rounded-[9999px] px-3 py-1 text-[12px] font-semibold tracking-[0.125px] {{ $location->is_active ? 'bg-[#e9fbf1] text-[#0f8a3b]' : 'bg-[#fff4df] text-[#a15c00]' }}">{{ $location->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                            <h3 class="mb-1 text-[15px] font-bold text-[rgba(0,0,0,0.95)]">{{ $location->location_name }}</h3>
                            <p class="mb-1 text-[13px] text-[#615d59]">{{ $location->description ? \Illuminate\Support\Str::limit($location->description, 64) : 'Belum ada deskripsi' }}</p>
                            <p class="mb-[10px] text-[13px] text-[#615d59]">Jumlah Mesin: {{ $location->machines_count }}</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    data-location-edit-open
                                    data-location-id="{{ $location->id }}"
                                    data-update-url="{{ route('master-lokasi.update', $location) }}"
                                    data-location-code="{{ $location->location_code }}"
                                    data-location-name="{{ $location->location_name }}"
                                    data-location-description="{{ $location->description }}"
                                    data-location-active="{{ $location->is_active ? 1 : 0 }}"
                                    class="h-9 rounded-[6px] border border-[rgba(0,0,0,0.1)] px-3 text-[12px] font-semibold"
                                    aria-label="Edit lokasi {{ $location->location_name }}"
                                >
                                    Edit
                                </button>
                                @if ($location->is_active)
                                    <button
                                        type="button"
                                        data-location-status-open
                                        data-status-url="{{ route('master-lokasi.deactivate', $location) }}"
                                        data-status-label="Nonaktifkan Lokasi"
                                        data-status-description="Lokasi akan dinonaktifkan dan tidak bisa dipilih untuk mesin baru."
                                        data-status-button="Nonaktifkan"
                                        data-status-variant="warning"
                                        class="h-9 rounded-[6px] bg-[#b86b00] px-3 text-[12px] font-semibold text-white"
                                    >
                                        Nonaktifkan
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        data-location-status-open
                                        data-status-url="{{ route('master-lokasi.activate', $location) }}"
                                        data-status-label="Aktifkan Lokasi"
                                        data-status-description="Lokasi akan diaktifkan kembali dan dapat dipilih untuk registrasi mesin baru."
                                        data-status-button="Aktifkan"
                                        data-status-variant="success"
                                        class="h-9 rounded-[6px] bg-[#15803d] px-3 text-[12px] font-semibold text-white"
                                    >
                                        Aktifkan
                                    </button>
                                @endif
                                <button
                                    type="button"
                                    data-location-delete-open
                                    data-delete-url="{{ route('master-lokasi.destroy', $location) }}"
                                    data-active-machines-count="{{ $location->active_machines_count }}"
                                    data-total-machines-count="{{ $location->machines_count }}"
                                    class="h-9 rounded-[6px] border border-[#ef4444] bg-white px-3 text-[12px] font-semibold text-[#dc2626]"
                                >
                                    Hapus Permanen
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div data-location-pagination>
                    <x-ui.numeric-pagination
                        :paginator="$locations"
                        summary-class="text-[13px] font-normal text-[#615d59]"
                        container-class="flex flex-col gap-4 border-t border-[var(--color-prime-border)] px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between"
                    />
                </div>
            @endif
        </x-ui.card>
    </section>

    <x-ui.modal id="location-create-modal" title="Tambah Lokasi" overlayClass="bg-black/35" panelClass="my-4 max-h-[calc(100vh-2rem)] max-w-[560px] overflow-y-auto rounded-[16px] bg-white p-6 shadow-[rgba(0,0,0,0.01)_0px_1px_3px,rgba(0,0,0,0.02)_0px_3px_7px,rgba(0,0,0,0.02)_0px_7px_15px,rgba(0,0,0,0.04)_0px_14px_28px,rgba(0,0,0,0.05)_0px_23px_52px]">
        <form action="{{ route('master-lokasi.store') }}" method="POST" class="space-y-5">
            @csrf
            <input type="hidden" name="modal_action" value="create">

            <div>
                <label for="create_location_code" class="mb-2 block text-[14px] font-semibold text-[rgba(0,0,0,0.95)]">Kode Lokasi <span class="text-[var(--color-prime-danger)]">*</span></label>
                <input id="create_location_code" type="text" name="location_code" value="{{ old('location_code') }}" placeholder="Contoh: LOC-GDA" class="h-11 w-full rounded-[6px] border border-[#dddddd] px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition placeholder:text-[#615d59] focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10">
                @error('location_code')
                    <p class="mt-2 text-sm text-[var(--color-prime-danger)]">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="create_location_name" class="mb-2 block text-[14px] font-semibold text-[rgba(0,0,0,0.95)]">Nama Lokasi <span class="text-[var(--color-prime-danger)]">*</span></label>
                <input id="create_location_name" type="text" name="location_name" value="{{ old('location_name') }}" placeholder="Contoh: Gedung A" class="h-11 w-full rounded-[6px] border border-[#dddddd] px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition placeholder:text-[#615d59] focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10">
                @error('location_name')
                    <p class="mt-2 text-sm text-[var(--color-prime-danger)]">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="create_is_active" class="mb-2 block text-[14px] font-semibold text-[rgba(0,0,0,0.95)]">Status</label>
                <select id="create_is_active" name="is_active" class="h-11 w-full rounded-[6px] border border-[#dddddd] px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10">
                    <option value="1" @selected((string) old('is_active', '1') === '1')>Aktif</option>
                    <option value="0" @selected((string) old('is_active') === '0')>Nonaktif</option>
                </select>
            </div>

            <div>
                <label for="create_description" class="mb-2 block text-[14px] font-semibold text-[rgba(0,0,0,0.95)]">Deskripsi</label>
                <textarea id="create_description" name="description" rows="4" placeholder="Informasi tambahan lokasi (opsional)" class="h-28 w-full rounded-[6px] border border-[#dddddd] px-[14px] py-3 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition placeholder:text-[#615d59] focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10">{{ old('description') }}</textarea>
            </div>

            <div class="flex justify-end gap-3 border-t border-[var(--color-prime-border)] pt-4">
                <x-ui.button variant="secondary" data-modal-close="location-create-modal">Batal</x-ui.button>
                <x-ui.button type="submit" class="h-11 rounded-[6px] bg-[#0075de] px-[18px] text-[14px] font-semibold text-white hover:bg-[#005bab]">Simpan</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="location-edit-modal" title="Edit Lokasi" overlayClass="bg-black/35" panelClass="my-4 max-h-[calc(100vh-2rem)] max-w-[560px] overflow-y-auto rounded-[16px] bg-white p-6 shadow-[rgba(0,0,0,0.01)_0px_1px_3px,rgba(0,0,0,0.02)_0px_3px_7px,rgba(0,0,0,0.02)_0px_7px_15px,rgba(0,0,0,0.04)_0px_14px_28px,rgba(0,0,0,0.05)_0px_23px_52px]">
        <form
            action="{{ $editingLocationData ? route('master-lokasi.update', $editingLocationData) : '#' }}"
            method="POST"
            class="space-y-5"
            data-location-edit-form
        >
            @csrf
            @method('PUT')
            <input type="hidden" name="modal_action" value="edit">
            <input type="hidden" name="location_id" value="{{ old('location_id', $editingLocationData?->id) }}" data-location-edit-field="id">

            <div>
                <label for="edit_location_code" class="mb-2 block text-[14px] font-semibold text-[rgba(0,0,0,0.95)]">Kode Lokasi <span class="text-[var(--color-prime-danger)]">*</span></label>
                <input
                    id="edit_location_code"
                    type="text"
                    name="location_code"
                    value="{{ old('location_code', $editingLocationData?->location_code) }}"
                    placeholder="Contoh: LOC-GDA"
                    data-location-edit-field="code"
                    class="h-11 w-full rounded-[6px] border border-[#dddddd] px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition placeholder:text-[#615d59] focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10"
                >
                @error('location_code')
                    @if (($modalState['action'] ?? null) === 'edit')
                        <p class="mt-2 text-sm text-[var(--color-prime-danger)]">{{ $message }}</p>
                    @endif
                @enderror
            </div>

            <div>
                <label for="edit_location_name" class="mb-2 block text-[14px] font-semibold text-[rgba(0,0,0,0.95)]">Nama Lokasi <span class="text-[var(--color-prime-danger)]">*</span></label>
                <input
                    id="edit_location_name"
                    type="text"
                    name="location_name"
                    value="{{ old('location_name', $editingLocationData?->location_name) }}"
                    placeholder="Contoh: Gedung A"
                    data-location-edit-field="name"
                    class="h-11 w-full rounded-[6px] border border-[#dddddd] px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition placeholder:text-[#615d59] focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10"
                >
                @error('location_name')
                    @if (($modalState['action'] ?? null) === 'edit')
                        <p class="mt-2 text-sm text-[var(--color-prime-danger)]">{{ $message }}</p>
                    @endif
                @enderror
            </div>

            <div>
                <label for="edit_is_active" class="mb-2 block text-[14px] font-semibold text-[rgba(0,0,0,0.95)]">Status</label>
                <select
                    id="edit_is_active"
                    name="is_active"
                    data-location-edit-field="active"
                    class="h-11 w-full rounded-[6px] border border-[#dddddd] px-[14px] py-0 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10"
                >
                    <option value="1" @selected((string) old('is_active', $editingLocationData?->is_active ? '1' : '0') === '1')>Aktif</option>
                    <option value="0" @selected((string) old('is_active', $editingLocationData?->is_active ? '1' : '0') === '0')>Nonaktif</option>
                </select>
            </div>

            <div>
                <label for="edit_description" class="mb-2 block text-[14px] font-semibold text-[rgba(0,0,0,0.95)]">Deskripsi</label>
                <textarea
                    id="edit_description"
                    name="description"
                    rows="4"
                    placeholder="Informasi tambahan lokasi (opsional)"
                    data-location-edit-field="description"
                    class="h-28 w-full rounded-[6px] border border-[#dddddd] px-[14px] py-3 text-[14px] text-[rgba(0,0,0,0.95)] outline-none transition placeholder:text-[#615d59] focus:border-[#097fe8] focus:ring-4 focus:ring-[#0075de]/10"
                >{{ old('description', $editingLocationData?->description) }}</textarea>
            </div>

            <div class="flex justify-end gap-3 border-t border-[var(--color-prime-border)] pt-4">
                <x-ui.button variant="secondary" data-modal-close="location-edit-modal">Batal</x-ui.button>
                <x-ui.button type="submit" class="h-11 rounded-[6px] bg-[#0075de] px-[18px] text-[14px] font-semibold text-white hover:bg-[#005bab]">Simpan Perubahan</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="location-status-modal" overlayClass="bg-black/35" panelClass="max-w-[560px] rounded-[16px] bg-white p-6 shadow-[rgba(0,0,0,0.01)_0px_1px_3px,rgba(0,0,0,0.02)_0px_3px_7px,rgba(0,0,0,0.02)_0px_7px_15px,rgba(0,0,0,0.04)_0px_14px_28px,rgba(0,0,0,0.05)_0px_23px_52px]">
        <div class="flex items-start gap-4">
            <div data-location-status-icon-wrap class="flex h-12 w-12 items-center justify-center rounded-full bg-[#fff4df] text-[#a15c00]">
                <svg data-location-status-icon class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 2v8"></path>
                    <path d="M7.8 4.8a8 8 0 1 0 8.4 0"></path>
                </svg>
            </div>
            <div class="flex-1">
                <h3 data-location-status-title class="text-[26px] font-semibold tracking-[-0.625px] text-[rgba(0,0,0,0.95)]">Nonaktifkan Lokasi</h3>
                <p data-location-status-description class="mt-3 text-[16px] leading-[1.5] text-[#615d59]">Lokasi akan dinonaktifkan dan tidak bisa dipilih untuk mesin baru.</p>
            </div>
        </div>

        <div class="mt-6 border-t border-[var(--color-prime-border)] pt-5">
            <form action="#" method="POST" class="flex justify-end gap-3" data-location-status-form>
                @csrf
                @method('PATCH')
                <x-ui.button variant="secondary" data-modal-close="location-status-modal">Batalkan</x-ui.button>
                <button type="submit" data-location-status-submit class="inline-flex h-11 items-center justify-center rounded-[6px] bg-[#b86b00] px-[18px] text-[14px] font-semibold text-white transition hover:bg-[#965500]">
                    Nonaktifkan
                </button>
            </form>
        </div>
    </x-ui.modal>

    <x-ui.modal id="location-delete-modal" overlayClass="bg-black/35" panelClass="max-w-[560px] rounded-[16px] bg-white p-6 shadow-[rgba(0,0,0,0.01)_0px_1px_3px,rgba(0,0,0,0.02)_0px_3px_7px,rgba(0,0,0,0.02)_0px_7px_15px,rgba(0,0,0,0.04)_0px_14px_28px,rgba(0,0,0,0.05)_0px_23px_52px]">
        <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[var(--color-prime-danger-soft)] text-[#dc2626]">
                <x-ui.icon name="trash" class="h-6 w-6" />
            </div>
            <div class="flex-1">
                <h3 data-location-delete-title class="text-[26px] font-semibold tracking-[-0.625px] text-[rgba(0,0,0,0.95)]">Hapus Lokasi</h3>
                <p data-location-delete-description class="mt-3 text-[16px] leading-[1.5] text-[#615d59]">Data lokasi akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.</p>
            </div>
        </div>

        <div class="mt-6 border-t border-[var(--color-prime-border)] pt-5">
            <form action="#" method="POST" class="flex justify-end gap-3" data-location-delete-form>
                @csrf
                @method('DELETE')
                <x-ui.button variant="secondary" data-modal-close="location-delete-modal">Batalkan</x-ui.button>
                <button type="submit" data-location-delete-submit class="inline-flex h-11 items-center justify-center rounded-[6px] bg-[#dc2626] px-[18px] text-[14px] font-semibold text-white transition hover:bg-[#b91c1c]">Hapus</button>
            </form>
        </div>
    </x-ui.modal>
</x-layouts.app>

