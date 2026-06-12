<x-layouts.app title="Detail Review Breakdown" heading="" :actor-name="$actorName" :role="$role" :show-operator-bottom-actions="false">
    <section class="mx-auto w-full max-w-6xl space-y-6">
        @if (session('flash_success'))<x-ui.alert variant="success">{{ session('flash_success') }}</x-ui.alert>@endif
        @if (session('flash_error'))<x-ui.alert variant="error">{{ session('flash_error') }}</x-ui.alert>@endif

        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <a href="{{ route('breakdown-review.index') }}" class="inline-flex items-center text-sm font-semibold text-[var(--color-prime-muted)]">
                <x-ui.icon name="arrow-right" class="mr-1 h-4 w-4 rotate-180" /> Kembali ke List
            </a>
            <div class="flex gap-2">
                <a href="{{ route('breakdown-review.edit', $breakdown->id) }}" class="rounded-xl bg-[var(--color-prime-soft)] px-4 py-2 text-sm font-semibold">Edit</a>
                <button type="button" class="rounded-xl border border-[var(--color-prime-danger)] px-4 py-2 text-sm font-semibold text-[var(--color-prime-danger)]" data-modal-open="delete-modal">Delete Breakdown</button>
            </div>
        </div>

        @php
            $downtimeMinutes = $breakdown->status === 'closed'
                ? (int) ($breakdown->downtime_minutes ?? 0)
                : (int) optional($breakdown->breakdown_at)->diffInMinutes(now());
            $hours = intdiv($downtimeMinutes, 60);
            $minutes = $downtimeMinutes % 60;
        @endphp

        <x-ui.card class="!rounded-2xl !p-5">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.08em] text-[var(--color-prime-muted)]">Kode Breakdown</p>
                    <p class="mt-1 text-4xl font-semibold tracking-tight">{{ $breakdown->breakdown_code }}</p>
                </div>
                <div class="flex items-center gap-6">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.08em] text-[var(--color-prime-muted)]">Status</p>
                        <x-ui.badge :variant="$breakdown->status === 'open' ? 'warning' : 'success'">{{ strtoupper($breakdown->status) }}</x-ui.badge>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.08em] text-[var(--color-prime-muted)]">Total Downtime</p>
                        <p class="text-2xl font-semibold text-[var(--color-prime-danger)]">{{ $hours }} Jam {{ $minutes }} Menit</p>
                    </div>
                </div>
            </div>
        </x-ui.card>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-ui.card class="!rounded-2xl !p-5">
                <h3 class="mb-3 text-sm font-bold uppercase tracking-[0.08em] text-[var(--color-prime-muted)]">Informasi Mesin</h3>
                <div class="space-y-3 text-sm">
                    <p><span class="text-[var(--color-prime-muted)]">Kode Mesin:</span> <span class="font-semibold">{{ $breakdown->machine?->machine_code ?? '-' }}</span></p>
                    <p><span class="text-[var(--color-prime-muted)]">Nama Mesin:</span> <span class="font-semibold">{{ $breakdown->machine_name_snapshot }}</span></p>
                    <p><span class="text-[var(--color-prime-muted)]">Lokasi:</span> <span class="font-semibold">{{ $breakdown->location_name_snapshot }}</span></p>
                </div>
            </x-ui.card>

            <x-ui.card class="!rounded-2xl !p-5">
                <h3 class="mb-3 text-sm font-bold uppercase tracking-[0.08em] text-[var(--color-prime-muted)]">Data Laporan Kerusakan (OPEN)</h3>
                <div class="space-y-3 text-sm">
                    <p><span class="text-[var(--color-prime-muted)]">Part:</span> <span class="font-semibold">{{ $breakdown->part_name_snapshot ?: ($breakdown->custom_part_name ?: '-') }}</span></p>
                    <p><span class="text-[var(--color-prime-muted)]">Problem:</span> <span class="font-semibold">{{ $breakdown->problem }}</span></p>
                    <p><span class="text-[var(--color-prime-muted)]">Catatan:</span> <span class="font-semibold">{{ $breakdown->open_note ?: 'Tidak ada catatan tambahan.' }}</span></p>
                    <p><span class="text-[var(--color-prime-muted)]">Waktu Kejadian:</span> <span class="font-semibold">{{ optional($breakdown->breakdown_at)->format('d M Y, H:i') }}</span></p>
                    <p><span class="text-[var(--color-prime-muted)]">PIC Pelapor:</span> <span class="font-semibold">{{ $breakdown->created_by_name_snapshot ?: '-' }}</span></p>
                </div>
            </x-ui.card>
        </div>

        <x-ui.card class="!rounded-2xl !p-5">
            <h3 class="mb-3 text-sm font-bold uppercase tracking-[0.08em] text-[var(--color-prime-muted)]">Bukti Media (Foto/Video)</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($breakdown->media as $media)
                    <div class="rounded-xl border border-[var(--color-prime-border)] p-3">
                        @if ($media->file_type === 'photo')
                            <img src="{{ asset('storage/' . $media->file_path) }}" alt="{{ $media->file_name }}" class="h-40 w-full rounded-lg object-cover">
                        @else
                            <video controls class="h-40 w-full rounded-lg object-cover">
                                <source src="{{ asset('storage/' . $media->file_path) }}" type="{{ $media->mime_type }}">
                            </video>
                        @endif
                        <p class="mt-2 text-sm font-semibold">{{ $media->file_name }}</p>
                        <p class="text-xs text-[var(--color-prime-muted)]">{{ strtoupper($media->file_type) }} • {{ number_format(((int) $media->file_size) / 1024 / 1024, 2) }} MB</p>
                        @if ($media->note)<p class="mt-1 text-xs text-[var(--color-prime-muted)] italic">{{ $media->note }}</p>@endif
                    </div>
                @empty
                    <p class="text-sm text-[var(--color-prime-muted)]">Tidak ada media yang diunggah.</p>
                @endforelse
            </div>
        </x-ui.card>

        <x-ui.card class="!rounded-2xl !p-5">
            <h3 class="mb-3 text-sm font-bold uppercase tracking-[0.08em] text-[var(--color-prime-muted)]">Data Penyelesaian (CLOSE)</h3>
            @if ($breakdown->status === 'closed')
                <div class="space-y-3 text-sm">
                    <p><span class="text-[var(--color-prime-muted)]">Root Cause:</span> <span class="font-semibold">{{ $breakdown->root_cause ?: '-' }}</span></p>
                    <p><span class="text-[var(--color-prime-muted)]">Action Taken:</span> <span class="font-semibold">{{ $breakdown->action_taken ?: '-' }}</span></p>
                    <p><span class="text-[var(--color-prime-muted)]">Countermeasure:</span> <span class="font-semibold">{{ $breakdown->countermeasure ?: '-' }}</span></p>
                    <p><span class="text-[var(--color-prime-muted)]">Closed At:</span> <span class="font-semibold">{{ optional($breakdown->closed_at)->format('d M Y, H:i') }}</span></p>
                    <p><span class="text-[var(--color-prime-muted)]">PIC Penyelesaian:</span> <span class="font-semibold">{{ $breakdown->closed_by_name_snapshot ?: '-' }}</span></p>
                </div>
            @else
                <p class="text-sm text-[var(--color-prime-muted)]">Breakdown belum ditutup. Data close belum tersedia.</p>
            @endif
        </x-ui.card>

        <x-ui.card class="!rounded-2xl !p-0">
            <div class="border-b border-[var(--color-prime-border)] p-5">
                <h3 class="text-sm font-bold uppercase tracking-[0.08em] text-[var(--color-prime-muted)]">Riwayat Perubahan (History)</h3>
            </div>
            @if ($breakdown->history->isEmpty())
                <p class="p-8 text-center text-sm text-[var(--color-prime-muted)]">Belum ada history perubahan pada data ini.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-[var(--color-prime-soft)] text-left">
                            <tr>
                                <th class="px-4 py-3">Waktu</th>
                                <th class="px-4 py-3">Admin</th>
                                <th class="px-4 py-3">Field</th>
                                <th class="px-4 py-3">Nilai Lama</th>
                                <th class="px-4 py-3">Nilai Baru</th>
                                <th class="px-4 py-3">Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($breakdown->history as $history)
                                <tr class="border-t border-[var(--color-prime-border)]">
                                    <td class="px-4 py-3">{{ optional($history->changed_at)->format('d M Y, H:i') }}</td>
                                    <td class="px-4 py-3">{{ $history->changer?->name ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $history->field_name }}</td>
                                    <td class="px-4 py-3">{{ $history->old_value ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $history->new_value ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $history->change_note ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </section>

    <x-ui.modal id="delete-modal" overlayClass="bg-black/35" panelClass="w-full max-w-md rounded-[16px] bg-white p-6 shadow-[rgba(0,0,0,0.01)_0px_1px_3px,rgba(0,0,0,0.02)_0px_3px_7px,rgba(0,0,0,0.02)_0px_7px_15px,rgba(0,0,0,0.04)_0px_14px_28px,rgba(0,0,0,0.05)_0px_23px_52px]">
        <div class="space-y-4">
            <div>
                <h4 class="text-2xl font-semibold text-[var(--color-prime-danger)]">Delete Breakdown</h4>
                <p class="mt-2 text-sm text-[var(--color-prime-muted)]">Data breakdown, media, dan history akan dihapus permanen dan tidak dapat dikembalikan.</p>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="rounded-xl bg-[var(--color-prime-soft)] px-4 py-2 font-semibold" data-modal-close="delete-modal">Batalkan</button>
                <form method="POST" action="{{ route('breakdown-review.destroy', $breakdown->id) }}">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-xl bg-[var(--color-prime-danger)] px-4 py-2 font-semibold text-white">Delete</button>
                </form>
            </div>
        </div>
    </x-ui.modal>
</x-layouts.app>
