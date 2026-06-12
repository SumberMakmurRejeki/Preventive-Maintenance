<x-layouts.app title="Edit Breakdown" heading="" :actor-name="$actorName" :role="$role" :show-operator-bottom-actions="false">
    <section class="mx-auto w-full max-w-4xl space-y-5 pb-24">
        @if (session('flash_error'))<x-ui.alert variant="error">{{ session('flash_error') }}</x-ui.alert>@endif
        @if ($errors->any())<x-ui.alert variant="error">{{ $errors->first() }}</x-ui.alert>@endif

        <div class="sticky top-0 z-20 flex items-center gap-3 border-b border-[var(--color-prime-border)] bg-[var(--color-prime-bg)]/95 py-3 backdrop-blur">
            <a href="{{ route('breakdown-review.show', $breakdown->id) }}" class="rounded-lg p-2 text-[var(--color-prime-primary)] hover:bg-[var(--color-prime-soft)]"><x-ui.icon name="arrow-right" class="h-5 w-5 rotate-180" /></a>
            <div>
                <p class="text-base font-semibold">Edit Breakdown</p>
                <p class="text-sm text-[var(--color-prime-muted)]">Koreksi data breakdown dan simpan catatan perubahan.</p>
            </div>
        </div>

        <x-ui.card class="!rounded-2xl !p-4">
            <p class="text-sm text-[var(--color-prime-muted)]">Kode: <span class="font-semibold text-[var(--color-prime-ink)]">{{ $breakdown->breakdown_code }}</span></p>
            <p class="text-sm text-[var(--color-prime-muted)]">Mesin: <span class="font-semibold text-[var(--color-prime-ink)]">{{ $breakdown->machine_name_snapshot }}</span></p>
        </x-ui.card>

        <form method="POST" action="{{ route('breakdown-review.update', $breakdown->id) }}" class="space-y-4" id="breakdown-edit-form">
            @csrf
            @method('PUT')
            <x-ui.card class="!rounded-2xl !p-5 space-y-4">
                <div>
                    <label class="mb-2 block text-sm font-semibold">Status <span class="text-[var(--color-prime-danger)]">*</span></label>
                    <select name="status" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">
                        <option value="open" @selected(old('status', $breakdown->status) === 'open')>OPEN</option>
                        <option value="closed" @selected(old('status', $breakdown->status) === 'closed')>CLOSED</option>
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold">Problem <span class="text-[var(--color-prime-danger)]">*</span></label>
                    <textarea name="problem" rows="3" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">{{ old('problem', $breakdown->problem) }}</textarea>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold">Open Note</label>
                    <textarea name="open_note" rows="2" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">{{ old('open_note', $breakdown->open_note) }}</textarea>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-semibold">Breakdown At <span class="text-[var(--color-prime-danger)]">*</span></label>
                        <input type="datetime-local" name="breakdown_at" value="{{ old('breakdown_at', optional($breakdown->breakdown_at)->format('Y-m-d\\TH:i')) }}" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold">Closed At</label>
                        <input type="datetime-local" name="closed_at" value="{{ old('closed_at', optional($breakdown->closed_at)->format('Y-m-d\\TH:i')) }}" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold">Root Cause</label>
                    <textarea name="root_cause" rows="2" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">{{ old('root_cause', $breakdown->root_cause) }}</textarea>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold">Action Taken</label>
                    <textarea name="action_taken" rows="2" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">{{ old('action_taken', $breakdown->action_taken) }}</textarea>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold">Countermeasure</label>
                    <textarea name="countermeasure" rows="2" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">{{ old('countermeasure', $breakdown->countermeasure) }}</textarea>
                </div>

                <div class="rounded-xl border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] p-3">
                    <label class="mb-2 block text-sm font-semibold">Catatan Perubahan (Wajib) <span class="text-[var(--color-prime-danger)]">*</span></label>
                    <textarea name="change_note" rows="2" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3" placeholder="Jelaskan alasan perubahan data...">{{ old('change_note') }}</textarea>
                </div>
            </x-ui.card>

            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-[var(--color-prime-border)] bg-[var(--color-prime-panel)]/95 px-4 py-3 backdrop-blur">
                <div class="mx-auto grid w-full max-w-4xl grid-cols-2 gap-3">
                    <a href="{{ route('breakdown-review.show', $breakdown->id) }}" class="inline-flex items-center justify-center rounded-xl border border-[var(--color-prime-border)] bg-white px-4 py-3 text-base font-semibold">Batal</a>
                    <button type="submit" id="submit-breakdown-edit" class="inline-flex items-center justify-center rounded-xl bg-[var(--color-prime-primary)] px-4 py-3 text-base font-semibold text-white">Simpan Perubahan</button>
                </div>
            </div>
        </form>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('breakdown-edit-form');
            const submitButton = document.getElementById('submit-breakdown-edit');
            form?.addEventListener('submit', () => {
                submitButton?.setAttribute('disabled', 'disabled');
                if (submitButton) submitButton.textContent = 'Memproses...';
            });
        });
    </script>
</x-layouts.app>
