<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'PRIME' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-[var(--color-prime-ink)]">
    <main class="flex min-h-screen w-full items-stretch">
        {{ $slot }}
    </main>
</body>
</html>
