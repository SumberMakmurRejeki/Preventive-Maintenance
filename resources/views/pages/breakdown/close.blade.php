<x-layouts.app title="Close Breakdown" heading="" :actor-name="$actorName" :role="$role" :show-operator-bottom-actions="false">
    @php
        $machineNameLength = \Illuminate\Support\Str::length((string) $breakdown->machine_name_snapshot);
        $machineTitleClass = match (true) {
            $machineNameLength <= 18 => 'text-[1.52rem] min-[390px]:text-[1.66rem] sm:text-[1.82rem]',
            $machineNameLength <= 24 => 'text-[1.36rem] min-[390px]:text-[1.5rem] sm:text-[1.66rem]',
            default => 'text-[1.2rem] min-[390px]:text-[1.32rem] sm:text-[1.48rem]',
        };

        $estimateMinutes = (int) optional($breakdown->breakdown_at)->diffInMinutes(now());
        $estimateHours = intdiv($estimateMinutes, 60);
        $estimateRemainMinutes = $estimateMinutes % 60;
        $partName = $breakdown->part_name_snapshot ?: ($breakdown->custom_part_name ?: 'Part Lainnya');
    @endphp

    <section class="font-sans mx-auto min-h-dvh w-full max-w-[30rem] space-y-6 bg-[#fffdfb] pb-28 pt-1 sm:space-y-7 sm:pb-32 sm:pt-2">
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
                    href="{{ route('machines.show', $breakdown->machine) }}"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full text-[#0075de] transition hover:bg-[#f2f9ff] sm:h-11 sm:w-11"
                    aria-label="Kembali"
                >
                    <x-ui.icon name="arrow-right" class="h-6 w-6 rotate-180 sm:h-7 sm:w-7" />
                </a>
                <p class="text-center text-[1.25rem] font-bold uppercase tracking-[-0.04em] text-[rgba(0,0,0,0.95)] min-[390px]:text-[1.35rem] sm:text-[1.5rem]">
                    Close Breakdown
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
                        {{ \Illuminate\Support\Str::upper($breakdown->machine?->machine_code ?? '-') }}
                    </p>

                    <p class="mt-5 text-[0.72rem] font-semibold uppercase tracking-[0.12em] text-white/75 sm:mt-6 sm:text-[0.78rem]">Nama Mesin</p>
                    <p class="mt-2 overflow-hidden text-ellipsis whitespace-nowrap font-bold leading-[1.05] tracking-[-0.05em] text-white {{ $machineTitleClass }}">
                        {{ \Illuminate\Support\Str::upper($breakdown->machine_name_snapshot) }}
                    </p>

                    <div class="mt-5 grid grid-cols-2 gap-2.5 min-[390px]:mt-6 min-[390px]:gap-3">
                        <div class="min-w-0 rounded-[0.9rem] border border-[rgba(255,255,255,0.22)] bg-[rgba(255,255,255,0.12)] px-3 py-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.08)] backdrop-blur-[6px] sm:rounded-[0.95rem] sm:px-3.5">
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.06em] text-white/75 sm:text-[0.7rem]">Status Breakdown</p>
                            <div class="mt-3 flex min-w-0 items-center gap-2 sm:mt-3.5 sm:gap-2.5">
                                <span
                                    class="inline-block h-3 w-3 shrink-0 rounded-full sm:h-3.5 sm:w-3.5"
                                    style="{{ $breakdown->status === 'open'
                                        ? 'background-color:#f59e0b; box-shadow:0 0 0 6px rgba(245,158,11,0.14);'
                                        : 'background-color:#1aae39; box-shadow:0 0 0 6px rgba(26,174,57,0.14);' }}"
                                    aria-hidden="true"
                                ></span>
                                <span class="min-w-0 text-[1rem] font-bold leading-none text-white min-[390px]:text-[1.1rem] sm:text-[1.2rem]">
                                    {{ strtoupper($breakdown->status) }}
                                </span>
                            </div>
                        </div>

                        <div class="min-w-0 rounded-[0.9rem] border border-[rgba(255,255,255,0.22)] bg-[rgba(255,255,255,0.12)] px-3 py-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.08)] backdrop-blur-[6px] sm:rounded-[0.95rem] sm:px-3.5">
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.06em] text-white/75 sm:text-[0.7rem]">Lokasi</p>
                            <div class="mt-3 flex min-w-0 items-center gap-2 sm:mt-3.5 sm:gap-2.5">
                                <x-ui.icon name="map-pin" class="h-4 w-4 shrink-0 text-white sm:h-4.5 sm:w-4.5" />
                                <span class="min-w-0 text-[1rem] font-bold leading-none text-white min-[390px]:text-[1.1rem] sm:text-[1.2rem]">
                                    {{ $breakdown->location_name_snapshot ?: '-' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="mb-4 text-[1.35rem] font-bold leading-[1.15] tracking-[-0.04em] text-[rgba(0,0,0,0.95)] sm:text-[1.5rem]">
                    Informasi Breakdown
                </h3>
                <div class="grid grid-cols-1 gap-3 min-[390px]:grid-cols-2 min-[390px]:gap-3.5">
                    <div class="min-w-0 rounded-[1rem] border border-[rgba(0,0,0,0.08)] bg-white px-4 py-3.5 shadow-[0_20px_42px_-34px_rgba(15,23,42,0.16)] sm:rounded-[1.1rem]">
                        <div class="inline-flex h-10 w-10 items-center justify-center rounded-[0.85rem] bg-[#fff4dc] text-[#d89000]">
                            <x-ui.icon name="wrench" class="h-5 w-5" />
                        </div>
                        <p class="mt-3.5 text-[0.78rem] font-medium leading-5 text-[#615d59] sm:text-[0.82rem]">
                            Part Mesin
                        </p>
                        <p class="mt-2.5 text-[1rem] font-bold leading-[1.2] tracking-[-0.03em] text-[#111111] min-[390px]:text-[1.06rem] sm:text-[1.12rem]">
                            {{ $partName }}
                        </p>
                    </div>

                    <div class="min-w-0 rounded-[1rem] border border-[rgba(0,0,0,0.08)] bg-white px-4 py-3.5 shadow-[0_20px_42px_-34px_rgba(15,23,42,0.16)] sm:rounded-[1.1rem]">
                        <div class="inline-flex h-10 w-10 items-center justify-center rounded-[0.85rem] bg-[#f2f9ff] text-[#0075de]">
                            <x-ui.icon name="clock" class="h-5 w-5" />
                        </div>
                        <p class="mt-3.5 text-[0.78rem] font-medium leading-5 text-[#615d59] sm:text-[0.82rem]">
                            Estimasi Downtime
                        </p>
                        <p class="mt-2.5 text-[1rem] font-bold leading-[1.2] tracking-[-0.03em] text-[#111111] min-[390px]:text-[1.06rem] sm:text-[1.12rem]">
                            {{ $estimateHours }}j {{ $estimateRemainMinutes }}m
                        </p>
                    </div>
                </div>

                <div class="mt-3 rounded-[1rem] border border-[rgba(0,0,0,0.08)] bg-white px-4 py-4 shadow-[0_20px_42px_-34px_rgba(15,23,42,0.16)] sm:rounded-[1.1rem]">
                    <div class="space-y-3">
                        <div>
                            <p class="text-[0.72rem] font-semibold tracking-[0.02em] text-[#615d59]">Kode Breakdown</p>
                            <p class="mt-1 text-[0.95rem] font-semibold text-[#111111]">{{ $breakdown->breakdown_code }}</p>
                        </div>
                        <div>
                            <p class="text-[0.72rem] font-semibold tracking-[0.02em] text-[#615d59]">Problem</p>
                            <p class="mt-1 text-[0.95rem] font-semibold text-[#111111]">{{ $breakdown->problem }}</p>
                        </div>
                        <div class="grid grid-cols-1 gap-3 min-[390px]:grid-cols-2">
                            <div>
                                <p class="text-[0.72rem] font-semibold tracking-[0.02em] text-[#615d59]">PIC</p>
                                <p class="mt-1 text-[0.95rem] font-semibold text-[#111111]">{{ $breakdown->created_by_name_snapshot ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[0.72rem] font-semibold tracking-[0.02em] text-[#615d59]">Tanggal Open Breakdown</p>
                                <p class="mt-1 text-[0.95rem] font-semibold text-[#111111]">{{ optional($breakdown->breakdown_at)->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if ($breakdown->status !== 'open')
                <x-ui.alert variant="success">Breakdown sudah ditutup. Form close tidak dapat diproses ulang.</x-ui.alert>
            @else
                <div>
                    <div class="mb-4 flex items-end justify-between gap-4 sm:mb-5">
                        <h3 class="text-[1.35rem] font-bold leading-[1.15] tracking-[-0.04em] text-[rgba(0,0,0,0.95)] sm:text-[1.5rem]">Form Penyelesaian</h3>
                        <span class="pb-1 text-[0.78rem] font-medium text-[#615d59] sm:text-[0.82rem]">Wajib diisi</span>
                    </div>

                    <form method="POST" action="{{ route('machines.breakdown-close.update', $breakdown) }}" class="space-y-4" id="breakdown-close-form">
                        @csrf
                        @method('PATCH')
                        <x-ui.card class="!rounded-[1.15rem] !border-[rgba(0,0,0,0.08)] !bg-white !p-4 !shadow-[0_20px_42px_-34px_rgba(15,23,42,0.16)] sm:!rounded-[1.35rem] sm:!p-5 !space-y-4">
                            <div>
                                <label class="mb-2 block text-sm font-semibold">Akar Masalah <span class="sr-only">Root Cause</span><span class="text-[var(--color-prime-danger)]">*</span></label>
                                <textarea name="root_cause" rows="3" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3" placeholder="Tuliskan penyebab utama breakdown...">{{ old('root_cause') }}</textarea>
                                @error('root_cause')<p class="mt-1 text-xs text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold">Tindakan Perbaikan <span class="sr-only">Action Taken</span><span class="text-[var(--color-prime-danger)]">*</span></label>
                                <textarea name="action_taken" rows="3" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3" placeholder="Tuliskan tindakan perbaikan...">{{ old('action_taken') }}</textarea>
                                @error('action_taken')<p class="mt-1 text-xs text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold">Pencegahan <span class="sr-only">Countermeasure</span><span class="text-[var(--color-prime-danger)]">*</span></label>
                                <textarea name="countermeasure" rows="3" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3" placeholder="Tuliskan pencegahan agar tidak berulang...">{{ old('countermeasure') }}</textarea>
                                @error('countermeasure')<p class="mt-1 text-xs text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold">Tanggal &amp; Jam Selesai <span class="text-[var(--color-prime-danger)]">*</span></label>
                                <input type="datetime-local" name="closed_at" value="{{ old('closed_at', now()->format('Y-m-d\\TH:i')) }}" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">
                                @error('closed_at')<p class="mt-1 text-xs text-[var(--color-prime-danger)]">{{ $message }}</p>@enderror
                            </div>
                        </x-ui.card>

                        <div class="fixed inset-x-0 bottom-0 z-20 bg-[linear-gradient(180deg,rgba(255,253,251,0)_0%,rgba(255,253,251,0.94)_24%,rgba(255,253,251,1)_100%)] px-3.5 pb-[calc(0.9rem+env(safe-area-inset-bottom))] pt-4 sm:px-5 sm:pb-[calc(1rem+env(safe-area-inset-bottom))] sm:pt-6">
                            <div class="mx-auto grid w-full max-w-[29rem] grid-cols-2 gap-3 sm:gap-4">
                                <a href="{{ route('machines.show', $breakdown->machine) }}" class="inline-flex h-[3.25rem] items-center justify-center rounded-[0.95rem] border border-[rgba(0,0,0,0.1)] bg-white px-3 text-[0.88rem] font-semibold text-[rgba(0,0,0,0.95)] shadow-[0_18px_36px_-34px_rgba(15,23,42,0.26)] transition hover:border-[rgba(0,117,222,0.22)] hover:bg-[#f9fcff] sm:h-14 sm:text-[0.95rem]">Batal</a>
                                <button type="submit" class="inline-flex h-[3.25rem] items-center justify-center rounded-[0.95rem] bg-[#0075de] px-3 text-[0.88rem] font-semibold text-white shadow-[0_24px_40px_-28px_rgba(0,117,222,0.62)] transition hover:bg-[#005bab] sm:h-14 sm:text-[0.95rem]" id="submit-close-breakdown">Close Breakdown</button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('breakdown-close-form');
            const submitButton = document.getElementById('submit-close-breakdown');

            form?.addEventListener('submit', () => {
                submitButton?.setAttribute('disabled', 'disabled');
                if (submitButton) {
                    submitButton.textContent = 'Memproses...';
                }
            });
        });
    </script>
</x-layouts.app>
