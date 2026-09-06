@php
    $isEdit = $checksheet !== null;
    $seedMachines = $machines->map(fn ($machine) => [
        'id' => $machine->id,
        'code' => $machine->machine_code,
        'name' => $machine->machine_name,
        'location' => $machine->location?->location_name,
    ])->values()->all();
@endphp

<section class="space-y-6" data-checksheet-wizard data-edit-id="{{ $checksheet?->id }}" data-preview-url="{{ route('master-checksheet.preview-schedule-update', $checksheet->id ?? 0) }}" data-apply-url="{{ route('master-checksheet.apply-schedule-update', $checksheet->id ?? 0) }}" data-seed-machines='@json($seedMachines)' data-seed-payload='@json($initialPayload ?? new stdClass())'>
    <a href="{{ route('master-checksheet.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-[var(--color-prime-muted)] hover:text-[var(--color-prime-ink)]">Kembali ke List</a>

    <h2 class="text-[2.35rem] font-semibold tracking-[-0.05em] text-[var(--color-prime-ink)]">{{ $title }}</h2>

    @if ($errors->any())
        <x-ui.alert variant="error">
            {{ $errors->first('wizard_payload') ?? $errors->first() }}
        </x-ui.alert>
    @endif

    <form action="{{ $action }}" method="POST" data-checksheet-form class="space-y-6">
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif

        <input type="hidden" name="wizard_payload" value="{{ old('wizard_payload') }}" data-checksheet-payload>

        <div class="rounded-xl border border-[var(--color-prime-border)] bg-white p-5 shadow-[0_12px_30px_-20px_rgba(15,23,42,0.2)]">
            <div class="mb-6 flex items-center justify-between" data-stepper-progress></div>

            <div data-step-panel="1" class="space-y-4">
                <h3 class="text-2xl font-semibold">Informasi Dasar Checksheet</h3>
                <label class="block">
                    <span class="mb-1 block text-sm font-semibold">Kode Checksheet *</span>
                    <input type="text" name="checksheet_code" value="{{ old('checksheet_code', $checksheet?->checksheet_code) }}" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3" required>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-semibold">Nama Checksheet *</span>
                    <input type="text" name="checksheet_name" value="{{ old('checksheet_name', $checksheet?->checksheet_name) }}" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3" required>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-semibold">Deskripsi</span>
                    <textarea name="description" rows="3" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">{{ old('description', $checksheet?->description) }}</textarea>
                </label>
                <label class="block max-w-xs">
                    <span class="mb-1 block text-sm font-semibold">Status</span>
                    <select name="is_active" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">
                        <option value="1" @selected((string) old('is_active', $checksheet?->is_active ? '1' : '1') === '1')>Aktif</option>
                        <option value="0" @selected((string) old('is_active', $checksheet?->is_active ? '1' : '1') === '0')>Nonaktif</option>
                    </select>
                </label>
            </div>

            <div data-step-panel="2" class="hidden space-y-4">
                <h3 class="text-2xl font-semibold">Pilih Mesin</h3>
                <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_16rem]">
                    <input type="search" placeholder="Cari nama atau kode mesin..." class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3" data-machine-search>
                    <select class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3" data-machine-location-filter>
                        <option value="">Semua Lokasi</option>
                    </select>
                </div>
                <div class="rounded-xl border border-[var(--color-prime-border)]" data-machine-list></div>
            </div>

            <div data-step-panel="3" class="hidden space-y-4">
                <h3 class="text-2xl font-semibold">Tentukan Part Mesin</h3>
                <div data-parts-builder class="space-y-4"></div>
            </div>

            <div data-step-panel="4" class="hidden space-y-4">
                <h3 class="text-2xl font-semibold">Standard Pengecekan</h3>
                <div data-standards-builder class="space-y-4"></div>
            </div>

            <div data-step-panel="5" class="hidden space-y-4">
                <h3 class="text-2xl font-semibold">Pengaturan Jadwal PM</h3>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold">Frekuensi *</span>
                        <select class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3" data-schedule-frequency>
                            <option value="">Pilih Frekuensi...</option>
                            <option value="daily">Harian (Daily)</option>
                            <option value="weekly">Mingguan (Weekly)</option>
                            <option value="monthly">Bulanan (Monthly)</option>
                        </select>
                    </label>
                    <div data-weekly-wrap class="hidden">
                        <span class="mb-1 block text-sm font-semibold">Pilih Hari *</span>
                        <div class="grid grid-cols-2 gap-2 text-sm" data-weekly-days></div>
                    </div>
                    <label class="block hidden" data-monthly-wrap>
                        <span class="mb-1 block text-sm font-semibold">Tanggal (1-31) *</span>
                        <input type="number" min="1" max="31" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3" data-monthly-day>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold">Mulai Jadwal PRIME *</span>
                        <input type="date" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3" data-schedule-operational>
                    </label>
                </div>
                <div class="rounded-xl border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="font-semibold">Preview Jadwal</p>
                        <button type="button" class="text-sm text-[var(--color-prime-primary)]" data-refresh-preview>Refresh Preview</button>
                    </div>
                    <div class="flex flex-wrap gap-2" data-schedule-preview></div>
                </div>
            </div>

            <div data-step-panel="6" class="hidden space-y-4">
                <h3 class="text-2xl font-semibold">Review Checksheet</h3>
                <div class="rounded-xl border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] p-4" data-review-box></div>
                {{-- Panel ini menampilkan hasil preview server sebelum jadwal edit diterapkan. --}}
                <div class="hidden rounded-xl border p-4" data-schedule-status></div>
                <div class="hidden overflow-x-auto rounded-xl border border-[var(--color-prime-border)]" data-schedule-impact></div>
                <button type="button" class="hidden rounded-xl bg-[var(--color-prime-primary)] px-5 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" data-apply-schedule>Terapkan Jadwal &amp; Simpan</button>
                <div class="hidden rounded-xl border p-4 text-sm" data-apply-status></div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <button type="button" class="rounded-xl border border-[var(--color-prime-border)] bg-white px-5 py-3 text-sm font-semibold" data-step-back>Kembali</button>
            <button type="button" class="rounded-xl bg-[var(--color-prime-primary)] px-5 py-3 text-sm font-semibold text-white" data-step-next>Selanjutnya</button>
            <button type="submit" class="hidden rounded-xl bg-[var(--color-prime-success)] px-5 py-3 text-sm font-semibold text-white" data-step-submit>{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Checksheet' }}</button>
        </div>
    </form>
</section>
