<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'PRIME' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/prime-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/prime-logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[var(--color-prime-bg)] text-[var(--color-prime-text)]">
    <main class="mx-auto flex min-h-screen w-full max-w-6xl items-stretch">
        {{ $slot }}
    </main>
</body>
</html>
