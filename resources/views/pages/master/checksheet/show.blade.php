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

        @if (session('flash_success'))
            <x-ui.alert variant="success" title="Informasi">{{ session('flash_success') }}</x-ui.alert>
        @endif

        @if (session('flash_error'))
            <x-ui.alert variant="error" title="Informasi">{{ session('flash_error') }}</x-ui.alert>
        @endif

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
                    <div><p class="text-[var(--color-prime-muted)]">{{ $schedule->operational_from === null ? 'Operational window' : 'Mulai Generate' }}</p><p class="font-semibold">{{ $schedule->operational_from === null ? 'Operational window belum ditentukan (legacy)' : optional($schedule->start_date)->format('Y-m-d') }}</p></div>
                    <div><p class="text-[var(--color-prime-muted)]">Berakhir Pada</p><p class="font-semibold">{{ optional($schedule->generate_until)->format('Y-m-d') }}</p></div>
                </div>
            @else
                <p class="mt-3 text-sm text-[var(--color-prime-muted)]">Belum ada jadwal.</p>
            @endif
        </x-ui.card>

        <x-ui.card class="space-y-5" title="Sinkronisasi Jadwal PM" subtitle="Preview ini tidak mengubah data. Terapkan hanya setelah seluruh perubahan ditinjau.">
            <p class="text-sm leading-6 text-[var(--color-prime-muted)]">Tanggal berstatus proses, menunggu review, disetujui, atau yang memiliki riwayat eksekusi dilindungi dan tidak akan dihapus atau diubah.</p>

            @if (! $checksheet->is_active)
                <x-ui.alert variant="warning">Checksheet nonaktif tidak dapat disinkronkan.</x-ui.alert>
            @elseif ($schedulePreview['selected'] === 0)
                <x-ui.alert variant="warning">Tidak ada jadwal PM aktif untuk disinkronkan.</x-ui.alert>
            @else
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-[var(--radius-card)] border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] p-4"><p class="text-sm text-[var(--color-prime-muted)]">Dibuat</p><p class="mt-1 text-2xl font-semibold text-[var(--color-prime-ink)]">{{ $schedulePreview['totals']['created'] }}</p></div>
                    <div class="rounded-[var(--radius-card)] border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] p-4"><p class="text-sm text-[var(--color-prime-muted)]">Dipulihkan</p><p class="mt-1 text-2xl font-semibold text-[var(--color-prime-ink)]">{{ $schedulePreview['totals']['restored'] }}</p></div>
                    <div class="rounded-[var(--radius-card)] border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] p-4"><p class="text-sm text-[var(--color-prime-muted)]">Dihapus</p><p class="mt-1 text-2xl font-semibold text-[var(--color-prime-ink)]">{{ $schedulePreview['totals']['removed'] }}</p></div>
                    <div class="rounded-[var(--radius-card)] border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] p-4"><p class="text-sm text-[var(--color-prime-muted)]">Tidak berubah</p><p class="mt-1 text-2xl font-semibold text-[var(--color-prime-ink)]">{{ $schedulePreview['totals']['unchanged'] }}</p></div>
                    <div class="rounded-[var(--radius-card)] border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] p-4"><p class="text-sm text-[var(--color-prime-muted)]">Konflik terlindungi</p><p class="mt-1 text-2xl font-semibold text-[var(--color-prime-ink)]">{{ $schedulePreview['totals']['conflicts'] }}</p></div>
                    <div class="rounded-[var(--radius-card)] border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] p-4"><p class="text-sm text-[var(--color-prime-muted)]">Tanggal diperiksa</p><p class="mt-1 text-2xl font-semibold text-[var(--color-prime-ink)]">{{ $schedulePreview['totals']['examined'] }}</p></div>
                    <div class="rounded-[var(--radius-card)] border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] p-4"><p class="text-sm text-[var(--color-prime-muted)]">Jadwal diperiksa</p><p class="mt-1 text-2xl font-semibold text-[var(--color-prime-ink)]">{{ $schedulePreview['selected'] }}</p></div>
                </div>

                <div class="space-y-3">
                    @foreach ($schedulePreview['schedules'] as $schedulePreviewRow)
                        @php
                            $previewSchedule = $schedulePreviewRow['schedule'];
                            $previewMachine = $previewSchedule->checksheetMachine->machine;
                            $previewResult = $schedulePreviewRow['result'];
                        @endphp
                        <div class="flex flex-col gap-3 rounded-[var(--radius-card)] border border-[var(--color-prime-border)] bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-semibold text-[var(--color-prime-ink)]">{{ $previewMachine->machine_code }} - {{ $previewMachine->machine_name }}</p>
                                <p class="mt-1 text-sm text-[var(--color-prime-muted)]">Jadwal #{{ $previewSchedule->id }}: {{ ucfirst($previewSchedule->frequency_type) }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <x-ui.badge variant="primary">+{{ $previewResult['created'] }} dibuat</x-ui.badge>
                                <x-ui.badge variant="success">{{ $previewResult['restored'] }} dipulihkan</x-ui.badge>
                                <x-ui.badge variant="warning">{{ $previewResult['removed'] }} dihapus</x-ui.badge>
                                @if ($previewResult['conflicts'] > 0)
                                    <x-ui.badge variant="danger">{{ $previewResult['conflicts'] }} konflik</x-ui.badge>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($schedulePreview['unresolved'] ?? false)
                    <x-ui.alert variant="warning" title="Jadwal belum siap disinkronkan">Terdapat jadwal dengan tanggal operasional yang belum ditetapkan. Silakan tetapkan tanggal operasional terlebih dahulu melalui menu edit.</x-ui.alert>
                @elseif ($schedulePreview['has_conflicts'])
                    <x-ui.alert variant="error" title="Konflik jadwal ditemukan">Sinkronisasi tidak dapat diterapkan sampai seluruh tanggal terlindungi ditinjau.</x-ui.alert>
                @elseif (! $schedulePreview['has_changes'])
                    <x-ui.alert variant="info">Tidak ada perubahan jadwal yang dapat diterapkan.</x-ui.alert>
                @else
                    <form action="{{ route('master-checksheet.reconcile-schedule-dates', $checksheet->id) }}" method="POST" class="flex flex-col items-start gap-4 border-t border-[var(--color-prime-border)] pt-5">
                        @csrf
                        <label class="flex items-start gap-3 text-sm text-[var(--color-prime-muted)]">
                            <input type="checkbox" name="confirmed" value="1" required class="mt-1 rounded border-[var(--color-prime-border)] text-[var(--color-prime-primary)] focus:ring-[var(--color-prime-primary)]">
                            <span>Saya telah meninjau preview dan memahami perubahan akan diterapkan ke seluruh jadwal aktif checksheet ini.</span>
                        </label>
                        <x-ui.button type="submit">Terapkan Sinkronisasi</x-ui.button>
                    </form>
                @endif
            @endif
        </x-ui.card>
    </section>
</x-layouts.app>
