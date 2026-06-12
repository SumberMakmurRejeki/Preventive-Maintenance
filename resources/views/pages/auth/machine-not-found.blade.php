<x-layouts.auth title="Mesin Tidak Ditemukan">
    <section class="mx-auto flex w-full max-w-xl items-center justify-center px-6 py-16">
        <div class="w-full rounded-2xl border border-[var(--color-prime-border)] bg-white p-8 text-center shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--color-prime-muted)]">QR Redirect</p>
            <h1 class="mt-3 text-3xl font-bold tracking-[-0.03em]">Mesin tidak ditemukan</h1>
            <p class="mt-3 text-sm leading-6 text-[var(--color-prime-muted)]">
                QR token atau kode mesin yang kamu buka tidak cocok dengan data master mesin PRIME.
            </p>
            <div class="mt-6 flex justify-center gap-3">
                <a href="{{ route('login') }}" class="rounded-md bg-[var(--color-prime-primary)] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[var(--color-prime-primary-active)]">Kembali ke Login</a>
                <a href="{{ route('forbidden') }}" class="rounded-md border border-[var(--color-prime-border)] bg-white px-4 py-3 text-sm font-semibold text-[var(--color-prime-text)] transition hover:border-[var(--color-prime-primary)] hover:text-[var(--color-prime-primary)]">Lihat 403</a>
            </div>
        </div>
    </section>
</x-layouts.auth>
