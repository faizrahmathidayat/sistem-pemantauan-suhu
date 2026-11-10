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
<body class="min-h-screen bg-gray-50 text-gray-800 antialiased" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
    @include('layouts.partials.sidebar')

    <div class="flex min-h-screen min-w-0 flex-col lg:pl-72 print:pl-0">
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
