<x-layouts.app
    title="Edit PM Review"
    heading="PM Management / PM Review"
    :actor-name="$actorName"
    :role="$role"
>
    @php
        $partGroups = $execution->items->groupBy(fn ($item) => (string) ($item->pm_checksheet_part_id ?? '0'));
    @endphp

    <section class="space-y-6">
        <div>
            <h2 class="text-[2rem] font-semibold tracking-[-0.04em] text-[var(--color-prime-ink)]">Edit Hasil Eksekusi PM</h2>
            <p class="mt-1 text-[var(--color-prime-muted)]">Koreksi nilai pengecekan, catatan part, dan isi change note wajib untuk audit.</p>
        </div>

        @if ($errors->any())
            <x-ui.alert variant="error">{{ $errors->first() }}</x-ui.alert>
        @endif

        <form action="{{ route('pm-review.update', $execution->id) }}" method="POST" class="space-y-5">
            @csrf
            @method('PUT')

            @foreach ($partGroups as $partKey => $items)
                @php $first = $items->first(); @endphp
                <x-ui.card>
                    <h3 class="border-b border-[var(--color-prime-border)] pb-2 text-lg font-semibold">{{ $first?->part_name_snapshot }}</h3>
                    <div class="mt-4 space-y-4">
                        @foreach ($items as $item)
                            <div>
                                <label class="mb-1 block text-sm font-semibold">{{ $item->standard_name_snapshot }} <span class="text-xs text-[var(--color-prime-muted)]">({{ $item->input_type_snapshot }})</span></label>
                                @if ($item->input_type_snapshot === 'action')
                                    <select name="items[{{ $item->id }}][action_value]" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">
                                        @foreach (($item->action_options_snapshot ?? []) as $option)
                                            <option value="{{ strtoupper($option) }}" @selected(strtoupper((string) $item->action_value) === strtoupper((string) $option))>{{ strtoupper($option) }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="number" step="0.01" name="items[{{ $item->id }}][number_value]" value="{{ old('items.' . $item->id . '.number_value', $item->number_value) }}" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">
                                    <p class="mt-1 text-xs text-[var(--color-prime-muted)]">
                                        @if ($item->input_type_snapshot === 'number')
                                            Target: {{ (float) $item->target_value_snapshot }} {{ $item->unit_snapshot }}
                                        @else
                                            Range: {{ (float) $item->min_value_snapshot }} - {{ (float) $item->max_value_snapshot }} {{ $item->unit_snapshot }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                        @endforeach

                        <div>
                            <label class="mb-1 block text-sm font-semibold">Catatan Part</label>
                            <textarea name="part_notes[{{ (int) ($first?->pm_checksheet_part_id ?? 0) }}]" rows="2" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">{{ old('part_notes.' . (int) ($first?->pm_checksheet_part_id ?? 0), $first?->note) }}</textarea>
                        </div>
                    </div>
                </x-ui.card>
            @endforeach

            <x-ui.card>
                <label class="mb-1 block text-sm font-semibold">Tanggal Submit PM <span class="text-[var(--color-prime-danger)]">*</span></label>
                <input
                    type="datetime-local"
                    name="submitted_at"
                    required
                    value="{{ old('submitted_at', optional($execution->submitted_at)->format('Y-m-d\\TH:i')) }}"
                    class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3"
                >

                <label class="mb-1 block text-sm font-semibold">Review Note (Opsional)</label>
                <textarea name="review_note" rows="3" class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3">{{ old('review_note', $execution->review_note) }}</textarea>

                <label class="mt-4 mb-1 block text-sm font-semibold">Catatan Perubahan (Change Note) <span class="text-[var(--color-prime-danger)]">*</span></label>
                <textarea name="change_note" rows="3" required class="w-full rounded-xl border border-[var(--color-prime-border)] px-4 py-3" placeholder="Contoh: Koreksi typo nilai input suhu.">{{ old('change_note') }}</textarea>
            </x-ui.card>

            <div class="flex items-center justify-end gap-2">
                <a href="{{ route('pm-review.show', $execution->id) }}" class="rounded-xl border border-[var(--color-prime-border)] px-4 py-3 text-sm font-semibold">Batal</a>
                <x-ui.button type="submit">Simpan Perubahan</x-ui.button>
            </div>
        </form>
    </section>
</x-layouts.app>
