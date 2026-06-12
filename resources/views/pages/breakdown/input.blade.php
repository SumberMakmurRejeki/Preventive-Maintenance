<x-layouts.app title="Input Breakdown" heading="" :actor-name="$actorName" :role="$role" :show-operator-bottom-actions="false">
    @php
        $machineNameLength = \Illuminate\Support\Str::length((string) $machine->machine_name);
        $machineTitleClass = match (true) {
            $machineNameLength <= 18 => 'text-[1.52rem] min-[390px]:text-[1.66rem] sm:text-[1.82rem]',
            $machineNameLength <= 24 => 'text-[1.36rem] min-[390px]:text-[1.5rem] sm:text-[1.66rem]',
            default => 'text-[1.2rem] min-[390px]:text-[1.32rem] sm:text-[1.48rem]',
        };
    @endphp

    <section class="font-sans mx-auto min-h-dvh w-full max-w-[30rem] space-y-6 bg-[#fffdfb] pb-28 pt-1 sm:space-y-7 sm:pb-32 sm:pt-2" data-breakdown-input-root>
        @if (session('flash_success'))<x-ui.alert variant="success">{{ session('flash_success') }}</x-ui.alert>@endif
        @if (session('flash_error'))<x-ui.alert variant="error">{{ session('flash_error') }}</x-ui.alert>@endif
        @if ($errors->any())<x-ui.alert variant="error">{{ $errors->first() }}</x-ui.alert>@endif

        <header class="border-b border-[rgba(0,0,0,0.08)] bg-white px-1 py-4 sm:py-5">
            <div class="flex items-center justify-between gap-3">
                <a
                    href="{{ route('machines.show', $machine) }}"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full text-[#0075de] transition hover:bg-[#f2f9ff] sm:h-11 sm:w-11"
                    aria-label="Kembali"
                ><x-ui.icon name="arrow-right" class="h-6 w-6 rotate-180 sm:h-7 sm:w-7" /></a>
                <p class="text-center text-[1.25rem] font-bold uppercase tracking-[-0.04em] text-[rgba(0,0,0,0.95)] min-[390px]:text-[1.35rem] sm:text-[1.5rem]">
                    Input Breakdown
                </p>
                <span class="w-10 shrink-0 sm:w-11" aria-hidden="true"></span>
            </div>
        </header>

        <div class="space-y-6 px-4 min-[390px]:px-5 sm:space-y-7">
            <div
                class="relative min-h-[14.75rem] overflow-hidden rounded-[1.35rem] px-5 py-5 text-white shadow-[0_24px_52px_-34px_rgba(0,91,171,0.42)] min-[390px]:min-h-[15.75rem] min-[390px]:rounded-[1.45rem] min-[390px]:px-5.5 sm:min-h-[16.25rem] sm:rounded-[1.55rem] sm:px-6 sm:py-5.5"
                style="background:linear-gradient(120deg,#005bab 0%,#0075de 44%,#2f9bff 100%);"
            >
                <div class="pointer-events-none absolute inset-y-0 right-[-2.5rem] w-[7rem] rounded-full bg-[rgba(255,255,255,0.05)] min-[390px]:right-[-2.75rem] min-[390px]:w-[9rem]"></div>
                <div class="pointer-events-none absolute right-[-2.75rem] top-[-0.75rem] h-[7.5rem] w-[7.5rem] rounded-full bg-[rgba(255,255,255,0.1)] min-[390px]:right-[-3.5rem] min-[390px]:h-[9rem] min-[390px]:w-[9rem]"></div>
                <div class="pointer-events-none absolute bottom-[-3rem] right-[0.75rem] h-[8.5rem] w-[5.5rem] rounded-full border border-[rgba(255,255,255,0.08)] bg-[rgba(255,255,255,0.05)] min-[390px]:h-[10rem] min-[390px]:w-[7rem]"></div>
                <div class="pointer-events-none absolute inset-x-0 top-0 h-14 bg-[linear-gradient(180deg,rgba(255,255,255,0.16)_0%,rgba(255,255,255,0)_100%)]"></div>

                <div class="relative">
                    <p class="text-[0.72rem] font-semibold uppercase tracking-[0.12em] text-white/75 sm:text-[0.78rem]">Kode Mesin</p>
                    <p class="mt-2 text-[1.52rem] font-bold leading-none tracking-[-0.06em] text-white min-[390px]:text-[1.66rem] sm:text-[1.82rem]">
                        {{ \Illuminate\Support\Str::upper($machine->machine_code) }}
                    </p>

                    <p class="mt-5 text-[0.72rem] font-semibold uppercase tracking-[0.12em] text-white/75 sm:mt-6 sm:text-[0.78rem]">Nama Mesin</p>
                    <p class="mt-2 overflow-hidden text-ellipsis whitespace-nowrap font-bold leading-[1.05] tracking-[-0.05em] text-white {{ $machineTitleClass }}">
                        {{ \Illuminate\Support\Str::upper($machine->machine_name) }}
                    </p>

                    <div class="mt-5 grid grid-cols-2 gap-2.5 min-[390px]:mt-6 min-[390px]:gap-3">
                        <div class="min-w-0 rounded-[0.9rem] border border-[rgba(255,255,255,0.22)] bg-[rgba(255,255,255,0.12)] px-3 py-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.08)] backdrop-blur-[6px] sm:rounded-[0.95rem] sm:px-3.5">
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.06em] text-white/75 sm:text-[0.7rem]">Status Mesin</p>
                            <div class="mt-3 flex min-w-0 items-center gap-2 sm:mt-3.5 sm:gap-2.5">
                                <span
                                    class="inline-block h-3 w-3 shrink-0 rounded-full sm:h-3.5 sm:w-3.5"
                                    style="{{ $machine->is_active
                                        ? 'background-color:#1aae39; box-shadow:0 0 0 6px rgba(26,174,57,0.14);'
                                        : 'background-color:#ef4444; box-shadow:0 0 0 6px rgba(239,68,68,0.15);' }}"
                                    aria-hidden="true"
                                ></span>
                                <span class="min-w-0 text-[1rem] font-bold leading-none text-white min-[390px]:text-[1.1rem] sm:text-[1.2rem]">
                                    {{ $machine->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                        </div>

                        <div class="min-w-0 rounded-[0.9rem] border border-[rgba(255,255,255,0.22)] bg-[rgba(255,255,255,0.12)] px-3 py-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.08)] backdrop-blur-[6px] sm:rounded-[0.95rem] sm:px-3.5">
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.06em] text-white/75 sm:text-[0.7rem]">Lokasi</p>
                            <div class="mt-3 flex min-w-0 items-center gap-2 sm:mt-3.5 sm:gap-2.5">
                                <x-ui.icon name="map-pin" class="h-4 w-4 shrink-0 text-white sm:h-4.5 sm:w-4.5" />
                                <span class="min-w-0 text-[1rem] font-bold leading-none text-white min-[390px]:text-[1.1rem] sm:text-[1.2rem]">
                                    {{ $machine->location?->location_name ?? '-' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <form method="POST" action="{{ route('breakdown-input.store', $machine) }}" enctype="multipart/form-data" class="space-y-4" id="breakdown-input-form">
            @csrf
            <x-ui.card class="!rounded-[1.15rem] !border-[rgba(0,0,0,0.08)] !bg-white !p-4 !shadow-[0_20px_42px_-34px_rgba(15,23,42,0.16)] sm:!rounded-[1.35rem] sm:!p-5 space-y-4">
                <div>
                    <label class="mb-2 block text-sm font-semibold">Part mesin <span class="sr-only">Part Mesin Terganggu</span><span class="text-[var(--color-prime-danger)]">*</span></label>
                    <select name="part_selection" id="part-selection" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">
                        <option value="">Pilih bagian mesin...</option>
                        @foreach ($parts as $part)
                            <option value="{{ $part->id }}" @selected(old('part_selection') == (string) $part->id)>{{ $part->part_name }}</option>
                        @endforeach
                        <option value="other" @selected(old('part_selection') === 'other')>Lainnya</option>
                    </select>
                    @error('part_selection')<p class="mt-1 text-xs text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror
                </div>

                <div id="custom-part-wrapper" class="{{ old('part_selection') === 'other' ? '' : 'hidden' }}">
                    <label class="mb-2 block text-sm font-semibold">Nama Part Lainnya <span class="text-[var(--color-prime-danger)]">*</span></label>
                    <input type="text" name="custom_part_name" value="{{ old('custom_part_name') }}" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3" placeholder="Tuliskan nama part...">
                    @error('custom_part_name')<p class="mt-1 text-xs text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold">Problem <span class="sr-only">Masalah / Gejala (Problem)</span><span class="text-[var(--color-prime-danger)]">*</span></label>
                    <textarea name="problem" rows="3" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3" placeholder="Jelaskan problem yang terjadi...">{{ old('problem') }}</textarea>
                    @error('problem')<p class="mt-1 text-xs text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold">Tanggal &amp; Jam <span class="sr-only">Waktu Kejadian</span><span class="text-[var(--color-prime-danger)]">*</span></label>
                    <input type="datetime-local" name="breakdown_at" value="{{ old('breakdown_at', now()->format('Y-m-d\\TH:i')) }}" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">
                    @error('breakdown_at')<p class="mt-1 text-xs text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold">Catatan <span class="sr-only">Catatan Tambahan</span><span class="text-[var(--color-prime-muted)] font-normal">(Opsional)</span></label>
                    <textarea name="open_note" rows="2" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3" placeholder="Tambahkan catatan jika diperlukan...">{{ old('open_note') }}</textarea>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold">Upload foto atau Video <span class="sr-only">Upload cepat (opsional)</span></label>
                    @error('media')<p class="mt-1 text-xs text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror
                    @error('media.*')<p class="mt-1 text-xs text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror

                    <div
                        id="temp-upload-trigger"
                        role="button"
                        tabindex="0"
                        class="mt-1 flex w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] px-4 py-4 text-center transition"
                        aria-label="Upload foto atau video"
                    >
                        <x-ui.icon name="arrow-down-tray" class="h-4 w-4 text-[var(--color-prime-muted)]" data-upload-idle-icon />
                        <span class="text-sm font-semibold text-[var(--color-prime-muted)]" data-upload-idle-text>Upload foto atau Video</span>
                        <span class="hidden items-center gap-2 text-sm font-semibold text-[var(--color-prime-primary)]" data-upload-loading>
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="3"></circle>
                                <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                            </svg>
                            Uploading...
                        </span>
                    </div>
                    <input type="file" id="temp-upload-file" class="hidden" accept="image/*,video/*">

                    <div id="temp-media-list" class="mt-3 space-y-2">
                        @foreach ($tempMedia as $temp)
                            <div class="flex items-center justify-between rounded-lg border border-[var(--color-prime-border)] bg-white px-3 py-2" data-temp-id="{{ $temp['id'] }}">
                                <div>
                                    <p class="text-xs font-semibold">{{ $temp['file_name'] }}</p>
                                    <p class="text-xs text-[var(--color-prime-muted)]">{{ strtoupper($temp['file_type']) }} • {{ number_format(((int) $temp['file_size']) / 1024 / 1024, 2) }} MB</p>
                                </div>
                                <button type="button" class="rounded-md px-2 py-1 text-xs font-semibold text-[var(--color-prime-danger)]" data-temp-remove="{{ $temp['id'] }}">Hapus</button>
                                <input type="hidden" name="temp_media_ids[]" value="{{ $temp['id'] }}">
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-ui.card>

            <div class="fixed inset-x-0 bottom-0 z-20 bg-[linear-gradient(180deg,rgba(255,253,251,0)_0%,rgba(255,253,251,0.94)_24%,rgba(255,253,251,1)_100%)] px-3.5 pb-[calc(0.9rem+env(safe-area-inset-bottom))] pt-4 sm:px-5 sm:pb-[calc(1rem+env(safe-area-inset-bottom))] sm:pt-6">
                <div class="mx-auto grid w-full max-w-[29rem] grid-cols-2 gap-3 sm:gap-4">
                    <a href="{{ route('machines.show', $machine) }}" class="inline-flex h-[3.25rem] items-center justify-center rounded-[0.95rem] border border-[rgba(0,0,0,0.1)] bg-white px-3 text-[0.88rem] font-semibold text-[rgba(0,0,0,0.95)] shadow-[0_18px_36px_-34px_rgba(15,23,42,0.26)] transition hover:border-[rgba(0,117,222,0.22)] hover:bg-[#f9fcff] sm:h-14 sm:text-[0.95rem]">Batal</a>
                    <button type="submit" class="inline-flex h-[3.25rem] items-center justify-center rounded-[0.95rem] bg-[#0075de] px-3 text-[0.88rem] font-semibold text-white shadow-[0_24px_40px_-28px_rgba(0,117,222,0.62)] transition hover:bg-[#005bab] sm:h-14 sm:text-[0.95rem]" id="submit-breakdown-button">Submit Breakdown</button>
                </div>
            </div>
        </form>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const machineCode = @json($machine->machine_code);
            const partSelect = document.getElementById('part-selection');
            const customPartWrapper = document.getElementById('custom-part-wrapper');
            const form = document.getElementById('breakdown-input-form');
            const submitButton = document.getElementById('submit-breakdown-button');
            const tempUploadFile = document.getElementById('temp-upload-file');
            const tempUploadTrigger = document.getElementById('temp-upload-trigger');
            const tempMediaList = document.getElementById('temp-media-list');
            const csrfToken = '{{ csrf_token() }}';
            let isUploading = false;

            const toggleCustomPart = () => {
                customPartWrapper?.classList.toggle('hidden', partSelect?.value !== 'other');
            };

            partSelect?.addEventListener('change', toggleCustomPart);
            toggleCustomPart();

            form?.addEventListener('submit', () => {
                submitButton?.setAttribute('disabled', 'disabled');
                if (submitButton) submitButton.textContent = 'Memproses...';
            });

            const appendTempMediaRow = (media) => {
                const row = document.createElement('div');
                row.className = 'flex items-center justify-between rounded-lg border border-[var(--color-prime-border)] bg-white px-3 py-2';
                row.setAttribute('data-temp-id', media.id);
                row.innerHTML = `
                    <div>
                        <p class="text-xs font-semibold">${media.file_name}</p>
                        <p class="text-xs text-[var(--color-prime-muted)]">${media.file_type.toUpperCase()} • ${(media.file_size / 1024 / 1024).toFixed(2)} MB</p>
                    </div>
                    <button type="button" class="rounded-md px-2 py-1 text-xs font-semibold text-[var(--color-prime-danger)]" data-temp-remove="${media.id}">Hapus</button>
                    <input type="hidden" name="temp_media_ids[]" value="${media.id}">
                `;
                tempMediaList?.appendChild(row);
            };

            const setUploadState = (loading) => {
                isUploading = loading;

                tempUploadTrigger?.classList.toggle('pointer-events-none', loading);
                tempUploadTrigger?.classList.toggle('opacity-70', loading);
                tempUploadTrigger?.querySelector('[data-upload-idle-icon]')?.classList.toggle('hidden', loading);
                tempUploadTrigger?.querySelector('[data-upload-idle-text]')?.classList.toggle('hidden', loading);

                const loadingNode = tempUploadTrigger?.querySelector('[data-upload-loading]');
                if (loadingNode) {
                    loadingNode.classList.toggle('hidden', !loading);
                    loadingNode.classList.toggle('inline-flex', loading);
                }
            };

            const openUploadPicker = () => {
                if (isUploading || !tempUploadFile) return;

                tempUploadFile.value = '';
                tempUploadFile.click();
            };

            tempUploadTrigger?.addEventListener('click', openUploadPicker);
            tempUploadTrigger?.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }

                event.preventDefault();
                openUploadPicker();
            });

            tempUploadFile?.addEventListener('change', async () => {
                const file = tempUploadFile.files?.[0];
                if (!file || isUploading) return;

                const formData = new FormData();
                formData.append('media_file', file);
                formData.append('machine_code', machineCode);

                try {
                    setUploadState(true);

                    const response = await fetch('{{ route('breakdown-media.upload') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(payload?.message ?? payload?.errors?.media_file?.[0] ?? 'Upload media gagal.');
                    }

                    appendTempMediaRow(payload.media);
                } catch (error) {
                    alert(error.message || 'Upload media gagal.');
                } finally {
                    setUploadState(false);
                    tempUploadFile.value = '';
                }
            });

            tempMediaList?.addEventListener('click', async (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;

                const mediaId = target.getAttribute('data-temp-remove');
                if (!mediaId) return;

                try {
                    const response = await fetch('{{ route('breakdown-media.destroy', ['mediaId' => 'TEMP_ID']) }}'.replace('TEMP_ID', mediaId), {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        },
                        body: new URLSearchParams({ machine_code: machineCode }),
                    });

                    if (!response.ok) throw new Error('Hapus media gagal.');

                    target.closest('[data-temp-id]')?.remove();
                } catch (error) {
                    alert(error.message || 'Hapus media gagal.');
                }
            });
        });
    </script>
</x-layouts.app>
