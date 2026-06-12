<x-layouts.app
    title="Halaman Mesin PRIME"
    heading=""
    :actor-name="$actorName"
    :role="$role"
    :show-operator-bottom-actions="false"
>
    @php
        $lastPmStatusLabel = $lastPm ? \Illuminate\Support\Str::headline((string) $lastPm->status) : 'Belum ada data';
        $nextPmStatusLabel = $nextPm ? \Illuminate\Support\Str::headline((string) $nextPm->status) : 'Tidak ada jadwal';
        $openBreakdownCount = $openBreakdowns->count();
        $machineNameLength = \Illuminate\Support\Str::length((string) $machine->machine_name);
        $machineTitleClass = match (true) {
            $machineNameLength <= 18 => 'text-[1.52rem] min-[390px]:text-[1.66rem] sm:text-[1.82rem]',
            $machineNameLength <= 24 => 'text-[1.36rem] min-[390px]:text-[1.5rem] sm:text-[1.66rem]',
            default => 'text-[1.2rem] min-[390px]:text-[1.32rem] sm:text-[1.48rem]',
        };

        $statusBadgeClasses = [
            'waiting_review' => 'bg-[#f2f9ff] text-[#097fe8]',
            'approved' => 'bg-[#f2f9ff] text-[#097fe8]',
            'scheduled' => 'bg-[#f2f9ff] text-[#097fe8]',
            'overdue' => 'bg-[#fff1dd] text-[#b96b00]',
            'in_progress' => 'bg-[#edf7ff] text-[#0b78e3]',
            'missed' => 'bg-[#fff1f2] text-[#c2410c]',
        ];
        $lastPmStatusClass = $statusBadgeClasses[$lastPm->status ?? ''] ?? 'bg-[#f6f5f4] text-[#615d59]';
        $nextPmStatusClass = $statusBadgeClasses[$nextPm->status ?? ''] ?? 'bg-[#f6f5f4] text-[#615d59]';
        $pmExecutorDisabled = (bool) ($pmExecutorDisabledReason || $transactionDisabledReason);
    @endphp

    <section
        class="font-sans mx-auto min-h-dvh w-full max-w-[30rem] space-y-6 bg-[#fffdfb] pb-28 pt-1 sm:space-y-7 sm:pb-32 sm:pt-2"
    >
        <p class="sr-only">Halaman Mesin PRIME</p>
        <p class="sr-only">Machine Status</p>
        <p class="sr-only">{{ $machine->machine_name }}</p>

        @if (session('flash_success'))
            <x-ui.alert variant="success">{{ session('flash_success') }}</x-ui.alert>
        @endif
        @if (session('flash_error'))
            <x-ui.alert variant="error">{{ session('flash_error') }}</x-ui.alert>
        @endif
        @if (session('flash_info'))
            <x-ui.alert>{{ session('flash_info') }}</x-ui.alert>
        @endif

        <header class="border-b border-[rgba(0,0,0,0.08)] bg-white px-1 py-4 sm:py-5">
            <div class="flex items-center justify-between gap-3">
                <a
                    href="{{ $backUrl }}"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full text-[#0075de] transition hover:bg-[#f2f9ff] sm:h-11 sm:w-11"
                    aria-label="Kembali"
                >
                    <x-ui.icon name="arrow-right" class="h-6 w-6 rotate-180 sm:h-7 sm:w-7" />
                </a>
                <p class="text-center text-[1.25rem] font-bold uppercase tracking-[-0.04em] text-[rgba(0,0,0,0.95)] min-[390px]:text-[1.35rem] sm:text-[1.5rem]">
                    Machine Profile
                </p>
                <span class="w-10 shrink-0 sm:w-11" aria-hidden="true"></span>
            </div>
        </header>

        @if ($pmExecutorDisabledReason)
            <x-ui.alert variant="warning">{{ $pmExecutorDisabledReason }}</x-ui.alert>
        @endif
        @if ($transactionDisabledReason)
            <x-ui.alert variant="warning">{{ $transactionDisabledReason }}</x-ui.alert>
        @endif

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
                    Jadwal Preventive Maintenance
                </h3>
                <div class="grid grid-cols-1 gap-3 min-[390px]:grid-cols-2 min-[390px]:gap-3.5">
                    <div class="min-w-0 rounded-[1rem] border border-[rgba(0,0,0,0.08)] bg-white px-4 py-3.5 shadow-[0_20px_42px_-34px_rgba(15,23,42,0.16)] sm:rounded-[1.1rem]">
                        <div class="inline-flex h-10 w-10 items-center justify-center rounded-[0.85rem] bg-[#f2f9ff] text-[#0075de]">
                            <x-ui.icon name="calendar" class="h-5 w-5" />
                        </div>
                        <p class="mt-3.5 text-[0.78rem] font-medium leading-5 text-[#615d59] sm:text-[0.82rem]">
                            Terakhir Melakukan PM
                        </p>
                        <p class="mt-2.5 overflow-hidden text-ellipsis whitespace-nowrap text-[0.92rem] font-bold leading-[1.2] tracking-[-0.02em] text-[#111111] tabular-nums min-[390px]:text-[0.98rem] sm:text-[1.02rem]">
                            {{ $lastPm?->submitted_at?->format('d M Y') ?? '-' }}
                        </p>
                        <span class="mt-4 inline-flex rounded-full px-2.5 py-1 text-[0.72rem] font-semibold {{ $lastPmStatusClass }}">
                            {{ $lastPmStatusLabel }}
                        </span>
                    </div>

                    <div class="min-w-0 rounded-[1rem] border border-[rgba(0,0,0,0.08)] bg-white px-4 py-3.5 shadow-[0_20px_42px_-34px_rgba(15,23,42,0.16)] sm:rounded-[1.1rem]">
                        <div class="inline-flex h-10 w-10 items-center justify-center rounded-[0.85rem] bg-[#fff4dc] text-[#d89000]">
                            <x-ui.icon name="calendar" class="h-5 w-5" />
                        </div>
                        <p class="mt-3.5 text-[0.78rem] font-medium leading-5 text-[#615d59] sm:text-[0.82rem]">
                            Jadwal PM Selanjutnya
                        </p>
                        <p class="mt-2.5 overflow-hidden text-ellipsis whitespace-nowrap text-[0.92rem] font-bold leading-[1.2] tracking-[-0.02em] text-[#111111] tabular-nums min-[390px]:text-[0.98rem] sm:text-[1.02rem]">
                            {{ $nextPm?->scheduled_date?->format('d M Y') ?? '-' }}
                        </p>
                        <span class="mt-4 inline-flex rounded-full px-2.5 py-1 text-[0.72rem] font-semibold {{ $nextPmStatusClass }}">
                            {{ $nextPmStatusLabel }}
                        </span>
                    </div>
                </div>
            </div>

            <div>
                <div class="mb-4 flex items-end justify-between gap-4 sm:mb-5">
                    <h3 class="text-[1.35rem] font-bold leading-[1.15] tracking-[-0.04em] text-[rgba(0,0,0,0.95)] sm:text-[1.5rem]">Open Breakdowns</h3>
                    <span class="pb-1 text-[0.78rem] font-medium text-[#615d59] sm:text-[0.82rem]">
                        {{ $openBreakdownCount }} {{ $openBreakdownCount === 1 ? 'Issue' : 'Issues' }}
                    </span>
                </div>

                @if ($openBreakdowns->isEmpty())
                    <div class="rounded-[1rem] border border-dashed border-[rgba(0,0,0,0.12)] bg-white px-5 py-7 text-center shadow-[0_20px_42px_-36px_rgba(15,23,42,0.12)] sm:rounded-[1.1rem]">
                        <div class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-[#f4f4f4] text-[#8f8f8f]">
                            <x-ui.icon name="file-search" class="h-6 w-6" />
                        </div>
                        <p class="mt-5 text-[0.82rem] font-medium text-[#615d59] sm:text-[0.88rem]">Tidak ada laporan breakdown</p>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach ($openBreakdowns as $breakdown)
                            <article class="rounded-[1.15rem] border border-[rgba(0,0,0,0.08)] bg-white px-4 py-4 shadow-[0_20px_42px_-34px_rgba(15,23,42,0.16)] sm:rounded-[1.35rem] sm:px-5 sm:py-5">
                                <div class="flex gap-4">
                                    <div class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-[0.9rem] bg-[#ffe9ed] text-[#ef4444] sm:h-14 sm:w-14 sm:rounded-[1rem]">
                                        <x-ui.icon name="exclamation-triangle" class="h-6 w-6 sm:h-7 sm:w-7" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="overflow-hidden text-[1rem] font-bold leading-[1.3] tracking-[-0.03em] text-[rgba(0,0,0,0.95)] sm:text-[1.2rem]"
                                            style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow-wrap:anywhere;"
                                        >
                                            {{ $breakdown->part_name_snapshot ?: ($breakdown->custom_part_name ?: 'Part Lainnya') }}
                                        </p>
                                        <p class="mt-2 text-[0.85rem] leading-5 text-[#615d59] sm:text-[0.92rem]">
                                            {{ $breakdown->breakdown_code }} &bull; {{ optional($breakdown->breakdown_at)->format('H:i') }}
                                        </p>
                                        <p class="mt-1 text-[0.85rem] leading-5 text-[#615d59] sm:text-[0.92rem]">
                                            PIC: {{ $breakdown->created_by_name_snapshot ?: 'Unknown' }}
                                        </p>
                                        <p class="mt-3 text-[0.95rem] leading-6 text-[rgba(0,0,0,0.95)] sm:mt-4 sm:text-[1rem] sm:leading-7">{{ $breakdown->problem }}</p>
                                        <div class="mt-4 flex justify-end">
                                            <a
                                                href="{{ route('machines.breakdown-close', $breakdown->id) }}"
                                                class="inline-flex items-center gap-2 rounded-full bg-[#f2f9ff] px-3.5 py-2 text-[0.85rem] font-semibold text-[#0075de] transition hover:bg-[#e4f2ff] sm:px-4 sm:text-[0.95rem] {{ ! $machine->is_active ? 'pointer-events-none opacity-50' : '' }}"
                                            >
                                                <span>Close Breakdown</span>
                                                <x-ui.icon name="arrow-right" class="h-4 w-4" />
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="fixed inset-x-0 bottom-0 z-20 bg-[linear-gradient(180deg,rgba(255,253,251,0)_0%,rgba(255,253,251,0.94)_24%,rgba(255,253,251,1)_100%)] px-3.5 pb-[calc(0.9rem+env(safe-area-inset-bottom))] pt-4 sm:px-5 sm:pb-[calc(1rem+env(safe-area-inset-bottom))] sm:pt-6">
            <div class="mx-auto grid w-full max-w-[29rem] grid-cols-2 gap-3 sm:gap-4">
                <a
                    href="{{ route('machines.breakdown-input', $machine) }}"
                    class="inline-flex h-[3.25rem] items-center justify-center gap-2 rounded-[0.95rem] border border-[rgba(0,0,0,0.1)] bg-white px-3 text-[0.88rem] font-semibold text-[rgba(0,0,0,0.95)] shadow-[0_18px_36px_-34px_rgba(15,23,42,0.26)] transition hover:border-[rgba(0,117,222,0.22)] hover:bg-[#f9fcff] sm:h-14 sm:text-[0.95rem] {{ ! $machine->is_active ? 'pointer-events-none opacity-50' : '' }}"
                >
                    <x-ui.icon name="wrench" class="h-4.5 w-4.5 text-[#0075de] sm:h-5 sm:w-5" />
                    <span>Input Breakdown</span>
                </a>

                <a
                    href="{{ route('pm-executor.show', $machine) }}"
                    class="inline-flex h-[3.25rem] items-center justify-center gap-2 rounded-[0.95rem] bg-[#0075de] px-3 text-[0.88rem] font-semibold text-white shadow-[0_24px_40px_-28px_rgba(0,117,222,0.62)] transition hover:bg-[#005bab] sm:h-14 sm:text-[0.95rem] {{ $pmExecutorDisabled ? 'pointer-events-none opacity-50' : '' }}"
                >
                    <x-ui.icon name="clipboard-check" class="h-4.5 w-4.5 text-white sm:h-5 sm:w-5" />
                    <span>PM Executor</span>
                </a>
            </div>
        </div>
    </section>
</x-layouts.app>
