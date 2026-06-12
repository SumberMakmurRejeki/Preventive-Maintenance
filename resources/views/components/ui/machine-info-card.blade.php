@props([
    'machineCode',
    'machineName',
    'locationName',
    'isActive' => true,
    'description' => null,
])

<x-ui.card {{ $attributes }} class="overflow-hidden bg-[linear-gradient(145deg,#1d337d_0%,#2455cc_44%,#4a76ee_100%)] text-white">
    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-100">{{ $machineCode }}</p>
    <h2 class="mt-3 text-3xl font-semibold tracking-[-0.03em]">{{ $machineName }}</h2>
    <p class="mt-4 max-w-2xl text-sm leading-7 text-blue-100/95">
        {{ $description ?: 'Landing page mesin aktif untuk membuka PM, input breakdown, atau close breakdown sesuai role pengguna.' }}
    </p>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-white/15 bg-white/10 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-blue-100">Lokasi</p>
            <p class="mt-2 text-base font-semibold">{{ $locationName }}</p>
        </div>
        <div class="rounded-2xl border border-white/15 bg-white/10 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-blue-100">Status Mesin</p>
            <p class="mt-2 text-base font-semibold">{{ $isActive ? 'Aktif' : 'Nonaktif' }}</p>
        </div>
    </div>
</x-ui.card>
