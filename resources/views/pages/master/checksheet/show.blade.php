<x-layouts.app title="Detail Checksheet" heading="PM Management / Master" :actor-name="$actorName" :role="$role">
    <section class="space-y-6">
        <a href="{{ route('master-checksheet.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-[var(--color-prime-muted)] hover:text-[var(--color-prime-ink)]">Kembali ke List</a>

        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-[2.2rem] font-semibold tracking-[-0.04em] text-[var(--color-prime-ink)]">[{{ $checksheet->checksheet_code }}] {{ $checksheet->checksheet_name }}</h2>
                <p class="mt-2 text-base text-[var(--color-prime-muted)]">{{ $checksheet->description ?: 'Tidak ada deskripsi.' }}</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('master-checksheet.edit', $checksheet->id) }}" class="rounded-xl border border-[var(--color-prime-border)] bg-white px-4 py-2 text-sm font-semibold">Edit</a>
                <form action="{{ route('master-checksheet.destroy', $checksheet->id) }}" method="POST" onsubmit="return confirm('Data checksheet akan dihapus permanen. Lanjutkan?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-xl border border-[var(--color-prime-danger)]/30 bg-white px-4 py-2 text-sm font-semibold text-[var(--color-prime-danger)]">Delete</button>
                </form>
            </div>
        </div>

        <x-ui.card class="space-y-5">
            <h3 class="text-xl font-semibold">Informasi Mesin & Standard</h3>
            @foreach ($checksheet->machineAssignments as $assignment)
                <div class="rounded-xl border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] p-4">
                    <h4 class="text-lg font-semibold text-[var(--color-prime-primary)]">{{ $assignment->machine?->machine_code }} - {{ $assignment->machine?->machine_name }}</h4>
                    <div class="mt-4 space-y-3">
                        @foreach ($assignment->parts as $part)
                            <div class="rounded-lg border border-[var(--color-prime-border)] bg-white p-3">
                                <p class="font-semibold">{{ $part->part_name }}</p>
                                @foreach ($part->standards as $standard)
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                                        <span class="text-[var(--color-prime-muted)]">{{ $standard->standard_name }}</span>
                                        <x-ui.badge variant="neutral">{{ $standard->input_type }}</x-ui.badge>
                                        @if ($standard->input_type === 'action')
                                            @foreach (($standard->action_options ?? []) as $option)
                                                <x-ui.badge variant="neutral">{{ $option }}</x-ui.badge>
                                            @endforeach
                                        @elseif ($standard->input_type === 'number')
                                            <span class="font-semibold">{{ rtrim(rtrim((string) $standard->target_value, '0'), '.') }} {{ $standard->unit }}</span>
                                        @else
                                            <span class="font-semibold">{{ rtrim(rtrim((string) $standard->min_value, '0'), '.') }} - {{ rtrim(rtrim((string) $standard->max_value, '0'), '.') }} {{ $standard->unit }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </x-ui.card>

        <x-ui.card>
            <h3 class="text-xl font-semibold">Pengaturan Jadwal PM</h3>
            @php $schedule = $checksheet->machineAssignments->first()?->schedules->first(); @endphp
            @if ($schedule)
                <div class="mt-4 grid gap-4 md:grid-cols-4 text-sm">
                    <div><p class="text-[var(--color-prime-muted)]">Frekuensi</p><p class="font-semibold">{{ ucfirst($schedule->frequency_type) }}</p></div>
                    <div><p class="text-[var(--color-prime-muted)]">Waktu</p><p class="font-semibold">{{ $schedule->frequency_type === 'weekly' ? implode(', ', $schedule->weekly_days ?? []) : ($schedule->frequency_type === 'monthly' ? 'Tanggal '.$schedule->monthly_day : 'Setiap hari') }}</p></div>
                    <div><p class="text-[var(--color-prime-muted)]">Mulai Generate</p><p class="font-semibold">{{ optional($schedule->start_date)->format('Y-m-d') }}</p></div>
                    <div><p class="text-[var(--color-prime-muted)]">Berakhir Pada</p><p class="font-semibold">{{ optional($schedule->generate_until)->format('Y-m-d') }}</p></div>
                </div>
            @else
                <p class="mt-3 text-sm text-[var(--color-prime-muted)]">Belum ada jadwal.</p>
            @endif
        </x-ui.card>
    </section>
</x-layouts.app>
