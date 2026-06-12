<x-layouts.auth title="403 Forbidden">
    <section class="mx-auto flex w-full max-w-xl items-center justify-center px-6 py-16">
        <div class="w-full rounded-2xl border border-[var(--color-prime-border)] bg-white p-8 text-center shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--color-prime-muted)]">Unauthorized</p>
            <h1 class="mt-3 text-3xl font-bold tracking-[-0.03em]">Akses ditolak</h1>
            <p class="mt-3 text-sm leading-6 text-[var(--color-prime-muted)]">
                Role aktif kamu tidak diizinkan membuka halaman ini. PRIME mencatat percobaan akses ini ke audit log.
            </p>
            <div class="mt-6 flex justify-center gap-3">
                <a href="{{ route('login') }}" class="rounded-md bg-[var(--color-prime-primary)] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[var(--color-prime-primary-active)]">Kembali ke Login</a>
            </div>
        </div>
    </section>
</x-layouts.auth>
