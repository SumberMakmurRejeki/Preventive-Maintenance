<x-layouts.app title="Halaman Mesin PRIME" heading="Halaman Mesin PRIME" :actor-name="$actorName" :role="$role">
    <section class="grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
        <x-ui.machine-info-card
            :machine-code="$machine->machine_code"
            :machine-name="$machine->machine_name"
            :location-name="$machine->location->location_name"
            :is-active="$machine->is_active"
            :description="$machine->description"
        />

        <div class="space-y-4">
            <x-ui.card title="Aksi Kontekstual">
                <div class="mt-4 space-y-3">
                    <x-ui.button
                        type="button"
                        :disabled="! $machine->is_active || $role !== 'operator'"
                        full
                    >
                        PM Executor
                    </x-ui.button>
                    <x-ui.button
                        type="button"
                        :disabled="! $machine->is_active"
                        variant="secondary"
                        full
                    >
                        Input Breakdown
                    </x-ui.button>
                    <x-ui.button
                        type="button"
                        :disabled="! $machine->is_active"
                        variant="secondary"
                        full
                    >
                        Close Breakdown
                    </x-ui.button>
                </div>
            </x-ui.card>

            <x-ui.card title="Aturan Aktif">
                <ul class="mt-4 space-y-3 text-sm leading-6 text-[var(--color-prime-muted)]">
                    <li>Admin boleh melihat halaman mesin, tetapi tidak boleh mengerjakan PM.</li>
                    <li>Operator boleh masuk dari QR atau machine access manual.</li>
                    <li>Mesin nonaktif tetap bisa dilihat, tetapi transaksi baru harus disabled.</li>
                </ul>
            </x-ui.card>
        </div>
    </section>
</x-layouts.app>
