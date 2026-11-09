<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
    @yield('banner')

    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3">
            <a href="{{ url('/dashboard') }}" class="font-semibold">🥬 {{ config('app.name') }}</a>
            <nav class="flex items-center gap-4 text-sm">
                <a href="{{ url('/dashboard') }}" class="hover:text-emerald-700">Dashboard</a>
                <a href="{{ url('/incidents') }}" class="hover:text-emerald-700">Riwayat Insiden</a>
                @auth
                    @php($unread = auth()->user()->unreadNotifications()->count())
                    <a href="{{ url('/notifications') }}" class="relative px-1 text-lg" aria-label="Notifikasi" title="Notifikasi">
                        🔔<span data-unread-badge @if ($unread === 0) hidden @endif
                              class="absolute -right-2 -top-1 rounded-full bg-red-600 px-1.5 text-[10px] font-semibold leading-4 text-white">{{ $unread }}</span>
                    </a>
                @endauth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-slate-500 hover:text-slate-800">Keluar</button>
                </form>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-4">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
