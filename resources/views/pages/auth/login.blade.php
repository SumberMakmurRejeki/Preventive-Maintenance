<x-layouts.auth title="Login PRIME">
    @php
        $activeTab = $errors->has('guest_name') || old('guest_name') ? 'guest' : 'staff';
    @endphp

    <section class="prime-login-hero hidden min-h-screen w-1/2 overflow-hidden px-14 py-12 text-white lg:flex xl:px-18 xl:py-14">
        <div class="prime-login-hero__circle prime-login-hero__circle--xl"></div>
        <div class="prime-login-hero__circle prime-login-hero__circle--lg"></div>
        <div class="prime-login-hero__circle prime-login-hero__circle--md"></div>
        <div class="prime-login-hero__curve prime-login-hero__curve--top"></div>
        <div class="prime-login-hero__curve prime-login-hero__curve--bottom"></div>
        <div class="relative z-10 flex w-full flex-col">
            <div class="flex items-center">
                <img
                    src="{{ asset('images/prime-logo.png') }}"
                    alt="PRIME logo"
                    class="h-auto w-full max-w-[17rem] drop-shadow-[0_16px_36px_rgba(0,0,0,0.2)]"
                >
            </div>

            <div class="my-auto"></div>
        </div>
    </section>

    <section class="flex min-h-screen w-full items-center justify-center bg-white px-6 py-10 sm:px-10 lg:w-1/2 lg:px-12 xl:px-18">
        <div class="w-full max-w-[42rem]" data-login-root data-login-tab="{{ $activeTab }}">
            <div class="mb-8 lg:hidden">
                <div class="flex justify-center">
                    <img
                        src="{{ asset('images/prime-logo.png') }}"
                        alt="PRIME logo"
                        class="h-auto w-full max-w-[13rem]"
                    >
                </div>
            </div>

            <div class="mb-9 max-w-[34rem]">
                <h2 class="text-[2.95rem] font-bold leading-[1.03] tracking-[-1.2px] text-[var(--color-prime-ink)] sm:text-[3.3rem]">
                    Selamat Datang Kembali
                </h2>
            </div>

            @if ($errors->any())
                <x-ui.alert variant="warning" class="mb-6">
                    {{ $errors->first() }}
                </x-ui.alert>
            @endif

            <div class="rounded-[1.05rem] border border-[var(--color-prime-border)] bg-[var(--color-prime-warm-white)] p-1.5 shadow-[0_10px_30px_-26px_rgba(15,23,42,0.25)]">
                <div class="grid grid-cols-2 gap-1">
                    <button
                        type="button"
                        data-login-switch="staff"
                        class="rounded-[0.9rem] px-5 py-4 text-[1rem] font-medium text-[var(--color-prime-muted)] transition duration-200"
                    >
                        Login dengan akun
                    </button>
                    <button
                        type="button"
                        data-login-switch="guest"
                        class="rounded-[0.9rem] px-5 py-4 text-[1rem] font-medium text-[var(--color-prime-muted)] transition duration-200"
                    >
                        Login sebagai Tamu
                    </button>
                </div>
            </div>

            <div class="mt-10">
                <form action="{{ route('login.store') }}" method="POST" data-login-panel="staff" class="space-y-7">
                    @csrf
                    <div>
                        <label for="username" class="mb-3 block text-[0.95rem] font-semibold uppercase tracking-[0.08em] text-[var(--color-prime-ink)]">
                            Username
                        </label>
                        <input
                            id="username"
                            name="username"
                            type="text"
                            value="{{ old('username') }}"
                            class="prime-login-input @error('username') border-[var(--color-prime-warning)]/45 ring-2 ring-[var(--color-prime-warning)]/10 @enderror"
                            autocomplete="username"
                        >
                    </div>

                    <div>
                        <div class="mb-3">
                            <label for="password" class="block text-[0.95rem] font-semibold uppercase tracking-[0.08em] text-[var(--color-prime-ink)]">
                                Kata Sandi
                            </label>
                        </div>
                        <div class="relative">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                class="prime-login-input pr-14 @error('password') border-[var(--color-prime-warning)]/45 ring-2 ring-[var(--color-prime-warning)]/10 @enderror"
                                autocomplete="current-password"
                            >
                            <button
                                type="button"
                                data-password-toggle="password"
                                class="absolute inset-y-0 right-0 flex w-14 items-center justify-center text-[var(--color-prime-muted)] transition hover:text-[var(--color-prime-ink)]"
                                aria-label="Tampilkan atau sembunyikan kata sandi"
                            >
                                <span data-password-icon="default">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z" />
                                        <circle cx="12" cy="12" r="3.2" />
                                    </svg>
                                </span>
                                <span data-password-icon="active" class="hidden">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="m4 4 16 16" />
                                        <path d="M10.7 6.2A10.5 10.5 0 0 1 12 6c6.1 0 9.5 6 9.5 6a17 17 0 0 1-3.1 3.8" />
                                        <path d="M14.5 14.7A3.2 3.2 0 0 1 9.3 9.5" />
                                        <path d="M6.1 6.8A16.5 16.5 0 0 0 2.5 12s3.4 6 9.5 6a10.6 10.6 0 0 0 3-.4" />
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="prime-login-submit prime-login-submit--staff" data-submit-loading>
                        <span>Login</span>
                        <span class="hidden" data-submit-spinner>
                            <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="4"></circle>
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                            </svg>
                        </span>
                    </button>
                </form>

                <form action="{{ route('guest-login.store') }}" method="POST" data-login-panel="guest" class="hidden space-y-6">
                    @csrf
                    <div>
                        <label for="guest_name" class="mb-3 block text-[0.95rem] font-semibold uppercase tracking-[0.08em] text-[var(--color-prime-ink)]">
                            Nama Lengkap
                        </label>
                        <input
                            id="guest_name"
                            name="guest_name"
                            type="text"
                            value="{{ old('guest_name') }}"
                            class="prime-login-input @error('guest_name') border-[var(--color-prime-warning)]/45 ring-2 ring-[var(--color-prime-warning)]/10 @enderror"
                            autocomplete="name"
                        >
                    </div>

                    <button type="submit" class="prime-login-submit prime-login-submit--guest" data-submit-loading>
                        <span>Masuk Sebagai Tamu</span>
                        <span class="hidden" data-submit-spinner>
                            <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="4"></circle>
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                            </svg>
                        </span>
                    </button>
                </form>
            </div>

        </div>
    </section>

</x-layouts.auth>
