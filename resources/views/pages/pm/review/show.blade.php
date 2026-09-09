<x-layouts.app
    title="Detail PM Review"
    heading="PM Management / PM Review"
    :actor-name="$actorName"
    :role="$role"
>
    @php
        $partGroups = $execution->items->groupBy(fn ($item) => (string) ($item->pm_checksheet_part_id ?? '0'));
        $mediaGroups = $execution->media->groupBy(fn ($media) => (string) ($media->pm_checksheet_part_id ?? '0'));
        $warningCount = $execution->items->where('is_warning', true)->count();
    @endphp

    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('pm-review.index') }}" class="text-sm font-semibold text-[var(--color-prime-muted)]">&larr; Kembali ke List</a>
            <div class="flex flex-wrap gap-2">
                <form
                    method="POST"
                    action="{{ route('pm-review.destroy', $execution->id) }}"
                    data-confirmable-form
                    data-confirm-variant="danger"
                    data-confirm-title="Hapus Data Permanen?"
                    data-confirm-description="Tindakan ini tidak dapat dibatalkan. Data eksekusi PM akan dihapus permanen, sementara histori mesin terkait tetap tersimpan."
                    data-confirm-action-label="Hapus Data"
                >
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger">Delete</x-ui.button>
                </form>
                @if ($execution->status !== 'approved')
                    <a href="{{ route('pm-review.edit', $execution->id) }}" class="inline-flex items-center rounded-xl border border-[var(--color-prime-border)] bg-white px-4 py-2 text-sm font-semibold">Edit Hasil</a>
                    <form
                        method="POST"
                        action="{{ route('pm-review.approve', $execution->id) }}"
                        data-confirmable-form
                        data-confirm-variant="info"
                        data-confirm-title="Approve Hasil PM?"
                        data-confirm-description="Hasil PM akan disetujui dan status berubah menjadi Approved. Pastikan data parameter, catatan, dan media sudah benar."
                        data-confirm-action-label="Approve PM"
                    >
                        @csrf
                        @method('PATCH')
                        <x-ui.button type="submit" variant="success">Approve PM</x-ui.button>
                    </form>
                @endif
            </div>
        </div>

        @if (session('flash_success'))<x-ui.alert variant="success">{{ session('flash_success') }}</x-ui.alert>@endif
        @if (session('flash_error'))<x-ui.alert variant="error">{{ session('flash_error') }}</x-ui.alert>@endif
        @if ($errors->any())<x-ui.alert variant="error">{{ $errors->first() }}</x-ui.alert>@endif

        <div class="grid gap-4 md:grid-cols-2">
            <x-ui.card>
                <h3 class="text-xs font-bold uppercase tracking-[0.15em] text-[var(--color-prime-muted)]">Informasi Eksekusi PM</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt>Status</dt><dd><x-ui.badge :variant="$execution->status === 'approved' ? 'success' : 'warning'">{{ $execution->status === 'approved' ? 'Approved' : 'Waiting Review' }}</x-ui.badge></dd></div>
                    <div class="flex justify-between"><dt>Tgl Jadwal PM</dt><dd class="font-semibold">{{ optional($execution->scheduleDate?->scheduled_date)->format('Y-m-d') ?? '-' }}</dd></div>
                    <div class="flex justify-between"><dt>Operator (PIC)</dt><dd class="font-semibold">{{ $execution->operator_name_snapshot ?? '-' }}</dd></div>
                    <div class="flex justify-between"><dt>Disubmit Pada</dt><dd class="font-semibold">{{ optional($execution->submitted_at)->format('Y-m-d H:i') ?? '-' }}</dd></div>
                    <div class="flex justify-between"><dt>Approved By</dt><dd class="font-semibold">{{ $execution->approved_by_name_snapshot ?? '-' }}</dd></div>
                    <div class="flex justify-between"><dt>Approved At</dt><dd class="font-semibold">{{ optional($execution->approved_at)->format('Y-m-d H:i') ?? '-' }}</dd></div>
                </dl>
            </x-ui.card>
            @php
                // Bundle identitas dibaca atomik agar histori tidak tercampur dengan master terkini.
                $hasHistoricalIdentity = collect([$execution->machine_code_snapshot, $execution->machine_name_snapshot, $execution->location_code_snapshot, $execution->location_name_snapshot])->every(fn ($value) => filled($value));
                $historicalUnavailable = 'Data historis tidak tersedia (legacy)';
            @endphp
            <x-ui.card>
                <h3 class="text-xs font-bold uppercase tracking-[0.15em] text-[var(--color-prime-muted)]">Identitas Mesin</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt>Kode Mesin</dt><dd class="font-semibold">{{ $hasHistoricalIdentity ? $execution->machine_code_snapshot : $historicalUnavailable }}</dd></div>
                    <div class="flex justify-between"><dt>Nama Mesin</dt><dd class="font-semibold">{{ $hasHistoricalIdentity ? $execution->machine_name_snapshot : $historicalUnavailable }}</dd></div>
                    <div class="flex justify-between"><dt>Lokasi</dt><dd class="font-semibold">{{ $hasHistoricalIdentity ? $execution->location_name_snapshot : $historicalUnavailable }}</dd></div>
                    <div class="flex justify-between"><dt>Total Warning</dt><dd class="font-semibold {{ $warningCount > 0 ? 'text-[var(--color-prime-danger)]' : 'text-[var(--color-prime-success)]' }}">{{ $warningCount > 0 ? $warningCount . ' Temuan' : '0 (Aman)' }}</dd></div>
                </dl>
            </x-ui.card>
        </div>

        @foreach ($partGroups as $partKey => $items)
            @php
                $first = $items->first();
                $partMedia = $mediaGroups[$partKey] ?? collect();
            @endphp
            <x-ui.card padding="p-0" class="overflow-hidden">
                <div class="border-b border-[var(--color-prime-border)] bg-[var(--color-prime-warm-white)] px-5 py-3">
                    <h4 class="font-semibold">{{ $first?->part_name_snapshot ?? 'Part' }}</h4>
                </div>
                <div class="p-5">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-[var(--color-prime-border)] text-left text-xs uppercase tracking-[0.1em] text-[var(--color-prime-muted)]">
                                    <th class="py-2">Standard</th>
                                    <th class="py-2">Tipe</th>
                                    <th class="py-2">Parameter</th>
                                    <th class="py-2">Hasil</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--color-prime-border)]">
                                @foreach ($items as $item)
                                    <tr>
                                        <td class="py-3 font-medium">{{ $item->standard_name_snapshot }}</td>
                                        <td class="py-3">{{ $item->input_type_snapshot }}</td>
                                        <td class="py-3 text-[var(--color-prime-muted)]">
                                            @if ($item->input_type_snapshot === 'number')
                                                Target: {{ (float) $item->target_value_snapshot }} {{ $item->unit_snapshot }}
                                            @elseif ($item->input_type_snapshot === 'range')
                                                Range: {{ (float) $item->min_value_snapshot }} - {{ (float) $item->max_value_snapshot }} {{ $item->unit_snapshot }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="py-3 font-semibold {{ $item->is_warning ? 'text-[var(--color-prime-danger)]' : '' }}">
                                            {{ $item->input_type_snapshot === 'action' ? $item->action_value : $item->number_value }}
                                            @if ($item->is_warning)
                                                <x-ui.badge variant="danger" class="ml-2">Warning</x-ui.badge>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 border-t border-[var(--color-prime-border)] pt-4">
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                            <div class="lg:col-span-3 rounded-xl border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] p-4">
                                <p class="text-xs font-bold uppercase tracking-[0.1em] text-[var(--color-prime-muted)]">Catatan Part</p>
                                <p class="mt-2 text-sm">{{ $first?->note ?: 'Tidak ada catatan.' }}</p>
                            </div>
                            <div class="lg:col-span-2 rounded-xl border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] p-4">
                                <p class="text-xs font-bold uppercase tracking-[0.1em] text-[var(--color-prime-muted)]">Media</p>
                                <div class="mt-2 grid gap-3">
                                    @forelse ($partMedia as $media)
                                        @php
                                            $mediaUrl = Storage::url($media->file_path);
                                        @endphp
                                        <div class="max-w-[360px] rounded-lg border border-[var(--color-prime-border)] bg-white p-2">
                                            @if ($media->file_type === 'photo')
                                                <a href="{{ $mediaUrl }}" target="_blank" rel="noopener noreferrer" class="block">
                                                    <img src="{{ $mediaUrl }}" alt="{{ $media->file_name }}" class="max-h-56 w-auto max-w-full rounded-[8px] border border-[rgba(0,0,0,0.1)] object-contain" loading="lazy">
                                                </a>
                                            @elseif ($media->file_type === 'video')
                                                <video class="max-h-56 w-full max-w-[360px] rounded-[8px] border border-[rgba(0,0,0,0.1)] bg-black object-contain" controls preload="metadata">
                                                    <source src="{{ $mediaUrl }}" type="{{ $media->mime_type ?: 'video/mp4' }}">
                                                    Browser tidak mendukung pemutaran video.
                                                </video>
                                            @else
                                                <a href="{{ $mediaUrl }}" target="_blank" rel="noopener noreferrer" class="block rounded-md border border-[var(--color-prime-border)] px-2 py-1 text-xs font-semibold text-[var(--color-prime-primary)]">
                                                    Buka Media
                                                </a>
                                            @endif
                                            <p class="mt-2 truncate text-xs font-semibold text-[var(--color-prime-ink)]" title="{{ $media->file_name }}">{{ $media->file_name }}</p>
                                        </div>
                                    @empty
                                        <p class="text-xs text-[var(--color-prime-muted)]">Tidak ada media</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        @endforeach

        <x-ui.card padding="p-0" class="overflow-hidden">
            <div class="border-b border-[var(--color-prime-border)] px-5 py-3">
                <h4 class="font-semibold">Riwayat Perubahan</h4>
            </div>
            @if ($execution->history->isEmpty())
                <div class="px-5 py-5 text-sm text-[var(--color-prime-muted)]">Belum ada riwayat perubahan.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[var(--color-prime-border)]">
                        <thead class="bg-[var(--color-prime-warm-white)]">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-[var(--color-prime-muted)]">Waktu</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-[var(--color-prime-muted)]">Admin</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-[var(--color-prime-muted)]">Field</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-[var(--color-prime-muted)]">Nilai Lama</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-[var(--color-prime-muted)]">Nilai Baru</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-[var(--color-prime-muted)]">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--color-prime-border)] bg-white">
                            @foreach ($execution->history as $history)
                                <tr>
                                    <td class="px-5 py-3 text-sm">{{ optional($history->changed_at)->format('Y-m-d H:i') }}</td>
                                    <td class="px-5 py-3 text-sm font-semibold">{{ $history->changer?->name ?? '-' }}</td>
                                    <td class="px-5 py-3 text-sm">{{ $history->field_name }}</td>
                                    <td class="px-5 py-3 text-sm">{{ $history->old_value }}</td>
                                    <td class="px-5 py-3 text-sm">{{ $history->new_value }}</td>
                                    <td class="px-5 py-3 text-sm">{{ $history->change_note }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </section>

    <div id="confirmation-dialog" class="fixed inset-0 z-[100] hidden items-center justify-center px-4" role="dialog" aria-modal="true" aria-labelledby="confirmation-title">
        <div class="absolute inset-0 bg-[rgba(0,0,0,0.4)]" data-confirm-close></div>
        <div
            class="relative w-full max-w-[400px] rounded-2xl bg-white p-6"
            style="box-shadow: rgba(0,0,0,0.01) 0px 1px 3px, rgba(0,0,0,0.02) 0px 3px 7px, rgba(0,0,0,0.02) 0px 7px 15px, rgba(0,0,0,0.04) 0px 14px 28px, rgba(0,0,0,0.05) 0px 23px 52px;"
        >
            <div class="flex items-start gap-3">
                <div id="confirm-icon-danger" class="hidden text-[#d93025]">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 3.5L21 19H3L12 3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path>
                        <path d="M12 9V13.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                        <path d="M12 17.2H12.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                    </svg>
                </div>
                <div id="confirm-icon-info" class="hidden text-[#0075de]">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"></circle>
                        <path d="M12 10.5V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                        <path d="M12 8H12.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 id="confirmation-title" class="text-[20px] font-semibold text-[rgba(0,0,0,0.95)]"></h3>
                    <p id="confirmation-description" class="mt-2 text-base font-normal text-[#615d59]"></p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" id="confirm-cancel" class="rounded-[4px] border border-[rgba(0,0,0,0.1)] bg-[rgba(0,0,0,0.05)] px-4 py-2 text-[rgba(0,0,0,0.95)]">Batal</button>
                <button type="button" id="confirm-action" class="rounded-[4px] px-4 py-2 text-white"></button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dialog = document.getElementById('confirmation-dialog');
            const titleNode = document.getElementById('confirmation-title');
            const descriptionNode = document.getElementById('confirmation-description');
            const cancelButton = document.getElementById('confirm-cancel');
            const actionButton = document.getElementById('confirm-action');
            const dangerIcon = document.getElementById('confirm-icon-danger');
            const infoIcon = document.getElementById('confirm-icon-info');

            if (!dialog || !titleNode || !descriptionNode || !cancelButton || !actionButton || !dangerIcon || !infoIcon) {
                return;
            }

            let activeForm = null;

            const closeDialog = () => {
                dialog.classList.add('hidden');
                dialog.classList.remove('flex');
                activeForm = null;
            };

            const openDialog = (form) => {
                activeForm = form;

                const variant = form.dataset.confirmVariant || 'info';
                const title = form.dataset.confirmTitle || 'Konfirmasi Aksi';
                const description = form.dataset.confirmDescription || 'Apakah Anda yakin ingin melanjutkan?';
                const actionLabel = form.dataset.confirmActionLabel || 'Lanjutkan';

                titleNode.textContent = title;
                descriptionNode.textContent = description;
                actionButton.textContent = actionLabel;

                dangerIcon.classList.toggle('hidden', variant !== 'danger');
                infoIcon.classList.toggle('hidden', variant !== 'info');

                if (variant === 'danger') {
                    actionButton.style.backgroundColor = '#d93025';
                } else {
                    actionButton.style.backgroundColor = '#0075de';
                }

                dialog.classList.remove('hidden');
                dialog.classList.add('flex');
            };

            document.querySelectorAll('[data-confirmable-form]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    openDialog(form);
                });
            });

            dialog.querySelectorAll('[data-confirm-close]').forEach((node) => {
                node.addEventListener('click', closeDialog);
            });

            cancelButton.addEventListener('click', closeDialog);

            actionButton.addEventListener('click', () => {
                if (!activeForm) {
                    closeDialog();
                    return;
                }

                const submittedForm = activeForm;
                closeDialog();
                submittedForm.submit();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeDialog();
                }
            });
        });
    </script>
</x-layouts.app>
