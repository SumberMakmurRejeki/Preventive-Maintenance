<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'PRIME' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[var(--color-prime-bg)] text-[var(--color-prime-ink)]">
    <div class="min-h-screen">
        <header class="border-b border-[var(--color-prime-border)] bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--color-prime-muted)]">PRIME</p>
                    <h1 class="text-lg font-bold tracking-[var(--tracking-body)]">{{ $heading ?? 'Dashboard' }}</h1>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-sm font-medium">{{ $actorName ?? 'User' }}</p>
                        <p class="text-xs uppercase tracking-[0.2em] text-[var(--color-prime-muted)]">{{ $role ?? 'guest' }}</p>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-[var(--radius-micro)] border border-[var(--color-prime-border)] bg-white px-4 py-2 text-sm font-semibold text-[var(--color-prime-ink)] transition hover:border-[var(--color-prime-primary)] hover:text-[var(--color-prime-primary)]"
                        >
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
