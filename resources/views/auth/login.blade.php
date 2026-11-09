<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 px-4 text-slate-800 antialiased">
    <form method="POST" action="{{ url('/login') }}" class="w-full max-w-sm space-y-4 rounded-xl bg-white p-6 shadow">
        @csrf
        <h1 class="text-xl font-semibold">🥬 {{ config('app.name') }}</h1>
        <p class="text-sm text-slate-500">Masuk untuk memantau suhu dan kelembapan gudang.</p>

        <label class="block text-sm">
            <span class="mb-1 block font-medium">Email</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-base">
        </label>
        @error('email')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror

        <label class="block text-sm">
            <span class="mb-1 block font-medium">Kata sandi</span>
            <input type="password" name="password" required autocomplete="current-password"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-base">
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1"> Ingat saya
        </label>

        <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 font-semibold text-white hover:bg-emerald-700">Masuk</button>
    </form>
</body>
</html>
