<x-layouts.app
    title="Kalender Maintenance"
    heading="Kalender Maintenance"
    subheading="Pantau jadwal preventive maintenance dan timeline breakdown mesin dalam satu kalender."
    :actor-name="$actorName"
    :role="$role"
    :show-operator-bottom-actions="$showOperatorBottomActions"
>
    <section class="space-y-6" data-calendar-page data-calendar-role="{{ $role }}" data-events-url="{{ route('calendar.events') }}">
        <div class="space-y-4">
            <div class="flex items-center gap-2 text-[15px] font-medium text-[rgba(0,0,0,0.95)]">
                <span>PM Management</span>
                <span class="text-[#a39e98]">/</span>
                <span>Kalender Maintenance</span>
            </div>
        </div>

        <x-ui.card>
            <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-6" data-calendar-filter-form>
                <div>
                    <label for="calendar-event-type" class="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-[var(--color-prime-muted)]">Tipe Event</label>
                    <select id="calendar-event-type" name="event_type" class="w-full rounded-xl border border-[var(--color-prime-border)] bg-white px-4 py-2.5 text-sm">
                        <option value="all">Semua</option>
                        <option value="pm">PM</option>
                        <option value="breakdown">Breakdown</option>
                    </select>
                </div>
                <div>
                    <label for="calendar-location" class="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-[var(--color-prime-muted)]">Lokasi</label>
                    <select id="calendar-location" name="location_id" class="w-full rounded-xl border border-[var(--color-prime-border)] bg-white px-4 py-2.5 text-sm">
                        <option value="">Semua Lokasi</option>
                        @foreach ($filterOptions['locations'] as $location)
                            <option value="{{ $location['id'] }}">{{ $location['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="calendar-machine" class="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-[var(--color-prime-muted)]">Mesin</label>
                    <select id="calendar-machine" name="machine_id" class="w-full rounded-xl border border-[var(--color-prime-border)] bg-white px-4 py-2.5 text-sm">
                        <option value="">Semua Mesin</option>
                        @foreach ($filterOptions['machines'] as $machine)
                            <option value="{{ $machine['id'] }}">{{ $machine['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="calendar-status" class="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-[var(--color-prime-muted)]">Status</label>
                    <select id="calendar-status" name="status" class="w-full rounded-xl border border-[var(--color-prime-border)] bg-white px-4 py-2.5 text-sm">
                        <option value="">Semua Status</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="in_progress">In Progress</option>
                        <option value="waiting_review">Waiting Review</option>
                        <option value="approved">Approved</option>
                        <option value="overdue">Overdue</option>
                        <option value="missed">Missed</option>
                        <option value="open">Open</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="xl:col-span-2 flex items-end justify-end gap-2">
                    <button type="button" class="rounded-xl border border-[var(--color-prime-border)] px-4 py-2.5 text-sm font-semibold text-[var(--color-prime-ink)] transition hover:bg-[var(--color-prime-soft)]" data-calendar-reset>
                        Reset Filter
                    </button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card>
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <span class="inline-flex items-center gap-2 rounded-full bg-[var(--color-prime-primary-soft)] px-3 py-1 text-[var(--color-prime-primary)]"><span class="h-2 w-2 rounded-full bg-[#3b82f6]"></span>PM Scheduled</span>
                    <span class="inline-flex items-center gap-2 rounded-full bg-[var(--color-prime-primary-soft)] px-3 py-1 text-[var(--color-prime-primary)]"><span class="h-2 w-2 rounded-full bg-[#f59e0b]"></span>PM In Progress</span>
                    <span class="inline-flex items-center gap-2 rounded-full bg-[var(--color-prime-primary-soft)] px-3 py-1 text-[var(--color-prime-primary)]"><span class="h-2 w-2 rounded-full bg-[#8b5cf6]"></span>PM Waiting Review</span>
                    <span class="inline-flex items-center gap-2 rounded-full bg-[var(--color-prime-primary-soft)] px-3 py-1 text-[var(--color-prime-primary)]"><span class="h-2 w-2 rounded-full bg-[#10b981]"></span>PM Approved</span>
                    <span class="inline-flex items-center gap-2 rounded-full bg-[var(--color-prime-primary-soft)] px-3 py-1 text-[var(--color-prime-primary)]"><span class="h-2 w-2 rounded-full bg-[#ef4444]"></span>PM Overdue</span>
                    <span class="inline-flex items-center gap-2 rounded-full bg-[var(--color-prime-primary-soft)] px-3 py-1 text-[var(--color-prime-primary)]"><span class="h-2 w-2 rounded-full bg-[#7f1d1d]"></span>PM Missed</span>
                    <span class="inline-flex items-center gap-2 rounded-full bg-[var(--color-prime-primary-soft)] px-3 py-1 text-[var(--color-prime-primary)]"><span class="h-2 w-2 rounded-full bg-[#dc2626]"></span>Breakdown Open</span>
                    <span class="inline-flex items-center gap-2 rounded-full bg-[var(--color-prime-primary-soft)] px-3 py-1 text-[var(--color-prime-primary)]"><span class="h-2 w-2 rounded-full bg-[#16a34a]"></span>Breakdown Closed</span>
                </div>

                <div class="relative min-h-[44rem]">
                    <div class="hidden pointer-events-none select-none absolute inset-0 z-20 items-center justify-center rounded-2xl bg-white/75 backdrop-blur-sm" data-calendar-loading>
                        <div class="select-none rounded-xl border border-[var(--color-prime-border)] bg-white px-4 py-3 text-sm font-medium text-[var(--color-prime-muted)]">Memuat event kalender...</div>
                    </div>

                    <div class="hidden pointer-events-none select-none absolute inset-0 z-20 items-center justify-center rounded-2xl bg-white/35" data-calendar-empty>
                        <x-ui.empty-state title="Tidak ada event" message="Tidak ada event PM atau breakdown pada periode/filter aktif." />
                    </div>

                    <div class="hidden pointer-events-none absolute inset-0 z-20 items-center justify-center rounded-2xl bg-white/35" data-calendar-error>
                        <div class="rounded-xl border border-[var(--color-prime-danger)]/30 bg-[var(--color-prime-danger-soft)] px-4 py-3 text-sm text-[var(--color-prime-danger)]" data-calendar-error-message>
                            Gagal memuat data kalender.
                        </div>
                    </div>

                    <div id="maintenance-calendar" class="rounded-2xl border border-[var(--color-prime-border)] bg-white p-3 sm:p-4"></div>
                </div>
            </div>
        </x-ui.card>
    </section>
</x-layouts.app>
