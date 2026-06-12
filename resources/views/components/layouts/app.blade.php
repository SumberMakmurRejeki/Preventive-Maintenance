<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'PRIME' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/prime-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/prime-logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="min-h-screen bg-[var(--color-prime-warm-white)] text-[var(--color-prime-ink)]"
    data-user-role="{{ $role ?? 'guest' }}"
    data-webpush-public-key="{{ ($role ?? 'guest') === 'admin' ? config('webpush.vapid.public_key', '') : '' }}"
    data-webpush-store-endpoint="{{ ($role ?? 'guest') === 'admin' ? route('admin-push-subscriptions.store') : '' }}"
    data-webpush-destroy-endpoint="{{ ($role ?? 'guest') === 'admin' ? route('admin-push-subscriptions.destroy') : '' }}"
>
@php
        $resolvedRole = $role ?? 'guest';
        $isOperatorShell = $resolvedRole === 'operator';
        $showOperatorBottomActions = $showOperatorBottomActions ?? true;
        $contentPadding = $isOperatorShell ? 'px-4 py-6 sm:px-6 sm:py-8 lg:px-8' : 'px-4 py-6 sm:px-6 sm:py-8 lg:px-8';
    @endphp

    <div id="prime-shell" class="min-h-screen bg-[var(--color-prime-warm-white)]">
        @unless ($isOperatorShell)
            <div id="prime-sidebar-overlay" class="fixed inset-0 z-30 hidden bg-slate-950/35 lg:hidden"></div>
            @include('partials.sidebar', ['role' => $resolvedRole])
        @endunless

        <div id="prime-main-content" class="{{ $isOperatorShell ? '' : 'lg:pl-[18.5rem]' }} min-h-screen bg-[var(--color-prime-warm-white)]">
            @include('partials.topbar', [
                'heading' => $heading ?? ($isOperatorShell ? null : 'Dashboard Monitoring'),
                'subheading' => $subheading ?? null,
                'actorName' => $actorName ?? 'User',
                'role' => $resolvedRole,
                'isOperatorShell' => $isOperatorShell,
            ])

            <main class="{{ $contentPadding }}">
                {{ $slot }}
            </main>
        </div>

        @if ($isOperatorShell && $showOperatorBottomActions)
            @include('partials.mobile-bottom-action', [
                'actions' => [
                    ['label' => 'PM Executor', 'variant' => 'primary', 'disabled' => false],
                    ['label' => 'Input Breakdown', 'variant' => 'secondary', 'disabled' => false],
                    ['label' => 'Close Breakdown', 'variant' => 'secondary', 'disabled' => false],
                ],
            ])
        @endif
    </div>
</body>
</html>
