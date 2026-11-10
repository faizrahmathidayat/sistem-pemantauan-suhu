<!DOCTYPE html>
{{-- Monitor publik untuk TV gudang: dokumen mandiri tanpa sesi, menu, tombol, atau tautan. Isinya digambar resources/js/monitor.js. --}}
<html lang="id" class="bg-[#0b1410]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $snapshot['warehouse'] }} · Monitor</title>
    @vite(['resources/css/app.css', 'resources/js/monitor.js'])
</head>
<body class="min-h-screen cursor-none bg-[#0b1410] text-white antialiased">
    <div id="monitor" data-url="{{ $dataUrl }}" class="flex min-h-screen flex-col gap-[1.2vw] p-[2vw]">
        <header class="flex items-center justify-between gap-6">
            <h1 id="monitor-title" class="min-w-0 truncate text-[clamp(1.5rem,3vw,3.5rem)] font-bold">{{ $snapshot['warehouse'] }}</h1>
            <div class="shrink-0 text-right">
                <p id="monitor-clock" class="text-[clamp(1.5rem,3vw,3.5rem)] leading-none font-bold tabular-nums"></p>
                <p id="monitor-date" class="mt-1 text-[clamp(0.9rem,1.3vw,1.6rem)] text-gray-400"></p>
            </div>
        </header>

        <div id="monitor-banner" class="rounded-3xl px-[2vw] py-[1.2vw]"></div>
        <div id="monitor-offline" hidden class="rounded-3xl bg-error-600 px-[2vw] py-[1vw] text-[clamp(1.1rem,2vw,2.4rem)] font-bold"></div>

        <main id="monitor-zones" class="grid flex-1 auto-rows-fr gap-[1.2vw]" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 30rem), 1fr))"></main>

        <footer id="monitor-footer" class="text-[clamp(0.8rem,1.1vw,1.3rem)] text-gray-400"></footer>
    </div>

    <script type="application/json" id="monitor-initial">@json($snapshot)</script>
</body>
</html>
