<x-layouts.app
    title="PM Executor"
    heading=""
    :actor-name="$actorName"
    :role="$role"
    :show-operator-bottom-actions="false"
>
    @php
        $machineNameLength = \Illuminate\Support\Str::length((string) $machine->machine_name);
        $machineTitleClass = match (true) {
            $machineNameLength <= 18 => 'text-[1.52rem] min-[390px]:text-[1.66rem] sm:text-[1.82rem]',
            $machineNameLength <= 24 => 'text-[1.36rem] min-[390px]:text-[1.5rem] sm:text-[1.66rem]',
            default => 'text-[1.2rem] min-[390px]:text-[1.32rem] sm:text-[1.48rem]',
        };
    @endphp

    <section
        class="font-sans mx-auto min-h-dvh w-full max-w-[30rem] space-y-6 bg-[#fffdfb] pb-28 pt-1 sm:space-y-7 sm:pb-32 sm:pt-2"
        data-pm-executor
    >
        @if (session('flash_success'))
            <x-ui.alert variant="success">{{ session('flash_success') }}</x-ui.alert>
        @endif
        @if (session('flash_error'))
            <x-ui.alert variant="error">{{ session('flash_error') }}</x-ui.alert>
        @endif

        @if ($errors->any())
            <x-ui.alert variant="error">{{ $errors->first() }}</x-ui.alert>
        @endif

        <header class="border-b border-[rgba(0,0,0,0.08)] bg-white px-1 py-4 sm:py-5">
            <div class="flex items-center justify-between gap-3">
                <a
                    href="{{ route('machines.show', $machine) }}"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full text-[#0075de] transition hover:bg-[#f2f9ff] sm:h-11 sm:w-11"
                    aria-label="Kembali"
                >
                    <x-ui.icon name="arrow-right" class="h-6 w-6 rotate-180 sm:h-7 sm:w-7" />
                </a>
                <p class="text-center text-[1.25rem] font-bold uppercase tracking-[-0.04em] text-[rgba(0,0,0,0.95)] min-[390px]:text-[1.35rem] sm:text-[1.5rem]">
                    PM Executor
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

            <div>
                <h3 class="mb-4 text-[1.35rem] font-bold leading-[1.15] tracking-[-0.04em] text-[rgba(0,0,0,0.95)] sm:text-[1.5rem]">
                    Informasi Preventive Maintenance
                </h3>
                <div class="grid grid-cols-1 gap-3 min-[390px]:grid-cols-2 min-[390px]:gap-3.5">
                    <div class="min-w-0 rounded-[1rem] border border-[rgba(0,0,0,0.08)] bg-white px-4 py-3.5 shadow-[0_20px_42px_-34px_rgba(15,23,42,0.16)] sm:rounded-[1.1rem]">
                        <div class="inline-flex h-10 w-10 items-center justify-center rounded-[0.85rem] bg-[#fff4dc] text-[#d89000]">
                            <x-ui.icon name="calendar" class="h-5 w-5" />
                        </div>
                        <p class="mt-3.5 text-[0.78rem] font-medium leading-5 text-[#615d59] sm:text-[0.82rem]">
                            Jadwal PM
                        </p>
                        <p class="mt-2.5 overflow-hidden text-ellipsis whitespace-nowrap text-[0.92rem] font-bold leading-[1.2] tracking-[-0.02em] text-[#111111] tabular-nums min-[390px]:text-[0.98rem] sm:text-[1.02rem]">
                            {{ $scheduleDate->scheduled_date->format('d M Y') }}
                        </p>
                    </div>

                    <div class="min-w-0 rounded-[1rem] border border-[rgba(0,0,0,0.08)] bg-white px-4 py-3.5 shadow-[0_20px_42px_-34px_rgba(15,23,42,0.16)] sm:rounded-[1.1rem]">
                        <div class="inline-flex h-10 w-10 items-center justify-center rounded-[0.85rem] bg-[#f2f9ff] text-[#0075de]">
                            <x-ui.icon name="clipboard-check" class="h-5 w-5" />
                        </div>
                        <p class="mt-3.5 text-[0.78rem] font-medium leading-5 text-[#615d59] sm:text-[0.82rem]">
                            Nama Checksheet
                        </p>
                        <p class="mt-2.5 text-[1rem] font-bold leading-[1.2] tracking-[-0.03em] text-[#111111] min-[390px]:text-[1.06rem] sm:text-[1.12rem]">
                            {{ $checksheetCode }}
                        </p>
                    </div>
                </div>
            </div>

            <div>
                <div class="mb-4 flex items-end justify-between gap-4 sm:mb-5">
                    <h3 class="text-[1.35rem] font-bold leading-[1.15] tracking-[-0.04em] text-[rgba(0,0,0,0.95)] sm:text-[1.5rem]">Checklist PM</h3>
                    <span class="pb-1 text-[0.78rem] font-medium text-[#615d59] sm:text-[0.82rem]">
                        {{ $parts->count() }} {{ $parts->count() === 1 ? 'Part' : 'Parts' }}
                    </span>
                </div>

                <form id="pm-executor-form" action="{{ route('pm-executor.start', $machine) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    @foreach ($parts as $part)
                <x-ui.card class="!rounded-[1.15rem] !border-[rgba(0,0,0,0.08)] !bg-white !p-4 !shadow-[0_20px_42px_-34px_rgba(15,23,42,0.16)] sm:!rounded-[1.35rem] sm:!p-5">
                    <h3 class="text-[1.05rem] font-bold leading-[1.2] tracking-[-0.03em] text-[rgba(15,23,42,0.98)] sm:text-[1.18rem]">
                        {{ $part->part_name }}
                    </h3>

                    <div class="mt-4 space-y-4 sm:mt-5 sm:space-y-6">
                        @foreach ($part->standards as $standard)
                            @php
                                $item = $existingItems[$standard->id] ?? null;
                                $currentAction = strtoupper((string) ($item?->action_value ?? ''));
                                $currentNumber = $item?->number_value;
                                $warningMessage = $item?->warning_message;
                            @endphp

                            <div class="pb-1 last:pb-0">
                                <label class="mb-2 block text-[13px] font-medium leading-relaxed text-slate-800 sm:mb-3 sm:text-[15px]">
                                    {{ $standard->standard_name }}
                                    @if ($standard->is_required)
                                        <span class="ml-2 text-[13px] font-medium text-[var(--color-prime-primary)] sm:text-[15px]">(Wajib)</span>
                                    @endif
                                </label>

                                @if ($standard->input_type === 'action')
                                    <input type="hidden" name="execution_action[{{ $standard->id }}]" value="{{ $currentAction }}" data-action-hidden="{{ $standard->id }}">
                                    <div class="flex flex-wrap gap-2 sm:gap-3" data-action-group="{{ $standard->id }}">
                                        @foreach (($standard->action_options ?? []) as $option)
                                            @php
                                                $upperOption = strtoupper((string) $option);
                                            @endphp
                                            <button
                                                type="button"
                                                data-action-option="{{ $standard->id }}"
                                                data-option-value="{{ $upperOption }}"
                                                class="min-w-[5.3rem] rounded-[0.8rem] border px-3 py-2.5 text-[13px] font-semibold tracking-[-0.02em] transition sm:min-w-[7rem] sm:rounded-[0.95rem] sm:px-4 sm:py-3 sm:text-[15px] {{ $currentAction === $upperOption ? 'border-[#1A73E8] bg-[#EFF6FF] text-[#1A73E8] shadow-[inset_0_0_0_1px_rgba(26,115,232,0.18)]' : 'border-[rgba(15,23,42,0.12)] bg-white text-[#0f172a]' }}"
                                            >
                                                {{ $upperOption }}
                                            </button>
                                        @endforeach
                                    </div>
                                    @error("execution_action.{$standard->id}")
                                        <p class="mt-2 text-xs text-[var(--color-prime-danger)]">{{ $message }}</p>
                                    @enderror
                                @elseif ($standard->input_type === 'number')
                                    <div class="grid gap-2 sm:grid-cols-[1fr_auto] sm:gap-3 sm:items-center">
                                        <input
                                            type="number"
                                            step="0.01"
                                            name="execution_number[{{ $standard->id }}]"
                                            value="{{ old("execution_number.{$standard->id}", $currentNumber) }}"
                                            class="rounded-[0.85rem] border border-[rgba(15,23,42,0.12)] px-3.5 py-3 text-[14px] shadow-[inset_0_1px_2px_rgba(15,23,42,0.03)] outline-none transition focus:border-[#1A73E8] focus:ring-4 focus:ring-[#1A73E8]/10 sm:rounded-[1rem] sm:px-4 sm:py-3.5 sm:text-[15px]"
                                            data-input-type="number"
                                            data-standard-id="{{ $standard->id }}"
                                            data-target="{{ $standard->target_value }}"
                                        />
                                        <p class="self-center text-[11px] font-medium text-[#64748b] sm:text-xs">Target: {{ (float) $standard->target_value }} {{ $standard->unit }}</p>
                                    </div>
                                    <p class="mt-2 hidden text-xs font-semibold text-[var(--color-prime-warning)]" data-warning="{{ $standard->id }}">Warning: Nilai tidak sesuai target.</p>
                                    @if ($warningMessage)
                                        <p class="mt-1 text-xs font-semibold text-[var(--color-prime-warning)]">{{ $warningMessage }}</p>
                                    @endif
                                    @error("execution_number.{$standard->id}")
                                        <p class="mt-2 text-xs text-[var(--color-prime-danger)]">{{ $message }}</p>
                                    @enderror
                                @else
                                    <div class="grid gap-2 sm:grid-cols-[1fr_auto] sm:gap-3 sm:items-center">
                                        <input
                                            type="number"
                                            step="0.01"
                                            name="execution_number[{{ $standard->id }}]"
                                            value="{{ old("execution_number.{$standard->id}", $currentNumber) }}"
                                            class="rounded-[0.85rem] border border-[rgba(15,23,42,0.12)] px-3.5 py-3 text-[14px] shadow-[inset_0_1px_2px_rgba(15,23,42,0.03)] outline-none transition focus:border-[#1A73E8] focus:ring-4 focus:ring-[#1A73E8]/10 sm:rounded-[1rem] sm:px-4 sm:py-3.5 sm:text-[15px]"
                                            data-input-type="range"
                                            data-standard-id="{{ $standard->id }}"
                                            data-min="{{ $standard->min_value }}"
                                            data-max="{{ $standard->max_value }}"
                                        />
                                        <p class="self-center text-[11px] font-medium text-[#64748b] sm:text-xs">{{ (float) $standard->min_value }} - {{ (float) $standard->max_value }} {{ $standard->unit }}</p>
                                    </div>
                                    <p class="mt-2 hidden text-xs font-semibold text-[var(--color-prime-warning)]" data-warning="{{ $standard->id }}">Warning: Nilai di luar batas range.</p>
                                    @if ($warningMessage)
                                        <p class="mt-1 text-xs font-semibold text-[var(--color-prime-warning)]">{{ $warningMessage }}</p>
                                    @endif
                                    @error("execution_number.{$standard->id}")
                                        <p class="mt-2 text-xs text-[var(--color-prime-danger)]">{{ $message }}</p>
                                    @enderror
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 border-t border-[rgba(15,23,42,0.08)] pt-4 sm:mt-6 sm:pt-5">
                        @php
                            $firstStandardId = $part->standards->first()?->id;
                            $partNoteValue = $firstStandardId && isset($existingItems[$firstStandardId])
                                ? $existingItems[$firstStandardId]->note
                                : null;
                        @endphp
                        <p class="mb-2 text-[13px] font-medium text-slate-900 sm:mb-3 sm:text-[15px]">Catatan</p>
                        <textarea
                            name="part_notes[{{ $part->id }}]"
                            rows="3"
                            class="w-full rounded-[0.85rem] border border-[rgba(15,23,42,0.12)] px-3.5 py-3.5 text-[14px] shadow-[inset_0_1px_2px_rgba(15,23,42,0.03)] outline-none transition focus:border-[#1A73E8] focus:ring-4 focus:ring-[#1A73E8]/10 sm:rounded-[1rem] sm:px-4 sm:py-4 sm:text-[15px]"
                            placeholder="Tambahkan catatan (opsional)..."
                            data-part-note="{{ $part->id }}"
                        >{{ old("part_notes.{$part->id}", $partNoteValue) }}</textarea>

                        <label class="mt-3 flex w-full cursor-pointer items-center justify-center gap-2.5 rounded-[0.85rem] border border-dashed border-[rgba(15,23,42,0.16)] bg-[linear-gradient(180deg,#ffffff_0%,#f8fbff_100%)] px-4 py-3.5 text-[14px] font-medium text-[#475569] sm:mt-4 sm:gap-3 sm:rounded-[1rem] sm:py-4 sm:text-[15px]" data-upload-trigger>
                            <x-ui.icon name="arrow-down-tray" class="h-4 w-4" data-upload-idle-icon />
                            <span data-upload-idle-text>Upload Foto atau Video</span>
                            <span class="hidden items-center gap-2" data-upload-loading>
                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="3"></circle>
                                    <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                                </svg>
                                Uploading...
                            </span>
                            <input type="file" name="media_files[{{ $part->id }}][]" multiple accept="image/*,video/*" class="hidden" data-upload-input="{{ $part->id }}" />
                        </label>

                        @php
                            $partMedia = $mediaByPart[$part->id] ?? collect();
                        @endphp
                        @if ($partMedia instanceof \Illuminate\Support\Collection && $partMedia->isNotEmpty())
                            <div class="mt-3 space-y-2" data-media-list="{{ $part->id }}">
                                @foreach ($partMedia as $media)
                                    <div class="flex items-center justify-between rounded-[1rem] border border-[var(--color-prime-border)] bg-white px-3 py-3 text-sm shadow-[0_8px_18px_rgba(15,23,42,0.04)]">
                                        <div>
                                            <p class="font-semibold">{{ $media->file_name }}</p>
                                            <p class="text-xs text-[var(--color-prime-muted)]">{{ strtoupper($media->file_type) }} • {{ number_format(((int) $media->file_size) / 1024 / 1024, 2) }} MB</p>
                                        </div>
                                        <button
                                            type="submit"
                                            form="delete-media-{{ $media->id }}"
                                            class="text-sm font-semibold text-[var(--color-prime-danger)]"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-3 space-y-2" data-media-list="{{ $part->id }}"></div>
                        @endif
                    </div>
                </x-ui.card>
                    @endforeach

            <div class="fixed inset-x-0 bottom-0 z-20 bg-[linear-gradient(180deg,rgba(255,253,251,0)_0%,rgba(255,253,251,0.94)_24%,rgba(255,253,251,1)_100%)] px-3.5 pb-[calc(0.9rem+env(safe-area-inset-bottom))] pt-4 sm:px-5 sm:pb-[calc(1rem+env(safe-area-inset-bottom))] sm:pt-6">
                <div class="mx-auto grid w-full max-w-[29rem] grid-cols-2 gap-3 sm:gap-4">
                    <button type="submit" formaction="{{ route('pm-executor.start', $machine) }}" class="inline-flex h-[3.25rem] items-center justify-center gap-2 rounded-[0.95rem] border border-[rgba(0,0,0,0.1)] bg-white px-3 text-[0.88rem] font-semibold text-[rgba(0,0,0,0.95)] shadow-[0_18px_36px_-34px_rgba(15,23,42,0.26)] transition hover:border-[rgba(0,117,222,0.22)] hover:bg-[#f9fcff] sm:h-14 sm:text-[0.95rem]">
                        <svg class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 4h11l3 3v13H5z" />
                            <path d="M8 4v6h8V4" />
                            <path d="M9 18h6" />
                        </svg>
                        Simpan Draft
                    </button>
                    <button type="submit" formaction="{{ route('pm-executor.submit', $machine) }}" class="inline-flex h-[3.25rem] items-center justify-center gap-2 rounded-[0.95rem] bg-[#0075de] px-3 text-[0.88rem] font-semibold text-white shadow-[0_24px_40px_-28px_rgba(0,117,222,0.62)] transition hover:bg-[#005bab] sm:h-14 sm:text-[0.95rem]">
                        <svg class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 3 10 14" />
                            <path d="m21 3-7 18-4-7-7-4 18-7Z" />
                        </svg>
                        Submit PM
                    </button>
                </div>
            </div>
                </form>
            </div>

        @foreach ($mediaByPart as $partMedia)
            @if ($partMedia instanceof \Illuminate\Support\Collection)
                @foreach ($partMedia as $media)
                    <form
                        id="delete-media-{{ $media->id }}"
                        action="{{ route('pm-executor.media.destroy', [$machine, $media]) }}"
                        method="POST"
                        class="hidden"
                        onsubmit="return confirm('Hapus media ini?')"
                    >
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach
            @endif
        @endforeach
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const root = document.querySelector('[data-pm-executor]');
            if (! root) {
                return;
            }

            root.querySelectorAll('[data-action-option]').forEach((button) => {
                button.addEventListener('click', () => {
                    const standardId = button.getAttribute('data-action-option');
                    const value = button.getAttribute('data-option-value') ?? '';
                    const hiddenInput = root.querySelector(`[data-action-hidden="${standardId}"]`);
                    const group = root.querySelector(`[data-action-group="${standardId}"]`);

                    if (! hiddenInput || ! group) {
                        return;
                    }

                    hiddenInput.value = value;

                    group.querySelectorAll('[data-action-option]').forEach((node) => {
                        node.classList.remove('border-[#1A73E8]', 'bg-[#EFF6FF]', 'text-[#1A73E8]', 'shadow-[inset_0_0_0_1px_rgba(26,115,232,0.18)]');
                        node.classList.add('border-[rgba(15,23,42,0.12)]', 'bg-white', 'text-[#0f172a]');
                        node.style.color = '#0f172a';
                        node.style.backgroundColor = '#ffffff';
                    });

                    button.classList.remove('border-[rgba(15,23,42,0.12)]', 'bg-white', 'text-[#0f172a]');
                    button.classList.add('border-[#1A73E8]', 'bg-[#EFF6FF]', 'text-[#1A73E8]', 'shadow-[inset_0_0_0_1px_rgba(26,115,232,0.18)]');
                    button.style.color = '#1A73E8';
                    button.style.backgroundColor = '#EFF6FF';
                });
            });

            const evaluateWarning = (input) => {
                const standardId = input.getAttribute('data-standard-id');
                const warningNode = root.querySelector(`[data-warning="${standardId}"]`);

                if (! warningNode) {
                    return;
                }

                const value = input.value === '' ? null : Number(input.value);
                if (value === null || Number.isNaN(value)) {
                    warningNode.classList.add('hidden');
                    return;
                }

                if (input.getAttribute('data-input-type') === 'number') {
                    const target = Number(input.getAttribute('data-target'));
                    warningNode.classList.toggle('hidden', value === target);
                    return;
                }

                const min = Number(input.getAttribute('data-min'));
                const max = Number(input.getAttribute('data-max'));
                warningNode.classList.toggle('hidden', value >= min && value <= max);
            };

            root.querySelectorAll('[data-input-type]').forEach((input) => {
                input.addEventListener('input', () => evaluateWarning(input));
                evaluateWarning(input);
            });

            const csrfToken = '{{ csrf_token() }}';
            const uploadUrl = @json(route('pm-executor.media.upload', $machine));

            const setUploadState = (partId, isLoading) => {
                const input = root.querySelector(`[data-upload-input="${partId}"]`);
                const trigger = input?.closest('[data-upload-trigger]');
                if (! trigger) {
                    return;
                }

                trigger.classList.toggle('pointer-events-none', isLoading);
                trigger.classList.toggle('opacity-70', isLoading);
                trigger.querySelector('[data-upload-idle-icon]')?.classList.toggle('hidden', isLoading);
                trigger.querySelector('[data-upload-idle-text]')?.classList.toggle('hidden', isLoading);

                const loadingNode = trigger.querySelector('[data-upload-loading]');
                if (loadingNode) {
                    loadingNode.classList.toggle('hidden', ! isLoading);
                    loadingNode.classList.toggle('inline-flex', isLoading);
                }
            };

            const createMediaRow = (partId, media) => {
                const mediaList = root.querySelector(`[data-media-list="${partId}"]`);
                if (! mediaList) {
                    return;
                }

                const deleteFormId = `delete-media-${media.id}`;

                const row = document.createElement('div');
                row.className = 'flex items-center justify-between rounded-[1rem] border border-[var(--color-prime-border)] bg-white px-3 py-3 text-sm shadow-[0_8px_18px_rgba(15,23,42,0.04)]';
                row.innerHTML = `
                    <div>
                        <p class="font-semibold">${media.file_name}</p>
                        <p class="text-xs text-[var(--color-prime-muted)]">${String(media.file_type ?? '').toUpperCase()} • ${(Number(media.file_size || 0) / 1024 / 1024).toFixed(2)} MB</p>
                    </div>
                    <button type="submit" form="${deleteFormId}" class="text-sm font-semibold text-[var(--color-prime-danger)]">Hapus</button>
                `;
                mediaList.prepend(row);

                const deleteForm = document.createElement('form');
                deleteForm.id = deleteFormId;
                deleteForm.action = @json(route('pm-executor.media.destroy', [$machine, 'MEDIA_ID'])).replace('MEDIA_ID', String(media.id));
                deleteForm.method = 'POST';
                deleteForm.className = 'hidden';
                deleteForm.setAttribute('onsubmit', "return confirm('Hapus media ini?')");
                deleteForm.innerHTML = `
                    <input type="hidden" name="_token" value="${csrfToken}">
                    <input type="hidden" name="_method" value="DELETE">
                `;
                root.appendChild(deleteForm);
            };

            const uploadFiles = async (partId, files) => {
                const partNote = root.querySelector(`[data-part-note="${partId}"]`)?.value ?? '';

                setUploadState(partId, true);
                try {
                    for (const file of files) {
                        const formData = new FormData();
                        formData.append('part_id', String(partId));
                        formData.append('part_note', partNote);
                        formData.append('media_file', file);

                        const response = await fetch(uploadUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                Accept: 'application/json',
                            },
                            body: formData,
                        });

                        const payload = await response.json().catch(() => ({}));
                        if (! response.ok) {
                            const message = payload?.message ?? payload?.errors?.media_file?.[0] ?? payload?.errors?.media_files?.[0] ?? 'Upload media gagal.';
                            throw new Error(message);
                        }

                        if (payload.media) {
                            createMediaRow(partId, payload.media);
                        }
                    }
                } catch (error) {
                    alert(error instanceof Error ? error.message : 'Upload media gagal.');
                } finally {
                    setUploadState(partId, false);
                    const uploadInput = root.querySelector(`[data-upload-input="${partId}"]`);
                    if (uploadInput) {
                        uploadInput.value = '';
                    }
                }
            };

            root.querySelectorAll('[data-upload-input]').forEach((input) => {
                input.addEventListener('change', () => {
                    const partId = input.getAttribute('data-upload-input');
                    if (! partId || ! input.files || input.files.length === 0) {
                        return;
                    }

                    uploadFiles(partId, Array.from(input.files));
                });
            });
        });
    </script>
</x-layouts.app>
