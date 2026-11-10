<!DOCTYPE html>
{{-- class "group/shell" + data-collapsed: keadaan sidebar dibaca CSS dari <html> (group-data-[collapsed=true]/shell) --}}
<html lang="id" class="group/shell">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    {{-- Pasang keadaan sidebar sebelum halaman digambar supaya tidak berkedip; gagal baca penyimpanan = tetap lebar --}}
    <script>try{if(localStorage.getItem('sidebarExpanded')==='false')document.documentElement.dataset.collapsed='true'}catch(e){}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-gray-50 text-gray-800 antialiased" x-data="shell" @keydown.escape.window="sidebarOpen = false">
    @include('layouts.partials.sidebar')

    <div class="sidebar-anim flex min-h-screen min-w-0 flex-col lg:pl-72 lg:group-data-[collapsed=true]/shell:pl-20 print:pl-0 print:group-data-[collapsed=true]/shell:pl-0">
        {{-- Header dan banner kritis menempel bersama di atas layar --}}
        <div class="sticky top-0 z-30 print:hidden">
            @include('layouts.partials.header')
            @yield('banner')
        </div>

        <main class="mx-auto w-full max-w-screen-2xl min-w-0 flex-1 p-4 md:p-6 print:max-w-none print:p-0">
            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
