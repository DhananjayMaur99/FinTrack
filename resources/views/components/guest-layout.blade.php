@props(['title' => 'FinTrack'])
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1e5a43">
    <title>{{ $title }} · FinTrack</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|ibm-plex-mono:500,600" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased" x-data>
    <div class="flex min-h-full flex-col items-center justify-center px-4 py-10">
        <div class="mb-6 flex items-center gap-2">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent text-sm font-semibold text-white">F</span>
            <span class="text-lg font-semibold tracking-tight">FinTrack</span>
        </div>

        <div class="sheet w-full max-w-sm px-6 py-7">
            {{ $slot }}
        </div>

        <p class="mt-6 text-xs text-ink-faint">Track what you spend, and why.</p>
    </div>
</body>
</html>
