<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-white text-gray-800 antialiased">
    <div class="grid min-h-screen lg:grid-cols-2">
        <div class="flex items-center justify-center px-4 py-10 sm:px-8">
            <form method="POST" action="{{ url('/login') }}" class="w-full max-w-md space-y-5">
                @csrf
                <div class="flex items-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-lg bg-brand-500 text-white"><x-icon name="thermometer" class="size-6" /></span>
                    <span class="text-lg font-semibold">{{ config('app.name') }}</span>
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-800">Masuk</h1>
                    <p class="mt-1 text-sm text-gray-500">Masuk untuk memantau suhu dan kelembapan gudang.</p>
                </div>

                <div>
                    <label for="email" class="form-label">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="form-input">
                    @error('email')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="form-label">Kata sandi</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password" class="form-input">
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="remember" value="1" class="size-4 rounded border-gray-300"> Ingat saya
                </label>

                <button type="submit" class="btn btn-primary w-full">Masuk</button>
            </form>
        </div>

        <div class="relative hidden flex-col items-center justify-center overflow-hidden bg-brand-500 p-12 text-white lg:flex">
            <div class="absolute inset-0 opacity-10" style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 48px 48px;"></div>
            <div class="relative max-w-sm text-center">
                <span class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-white/10"><x-icon name="thermometer" class="size-9" /></span>
                <h2 class="mt-6 text-2xl font-semibold">{{ config('app.name') }}</h2>
                <p class="mt-3 text-sm text-brand-200">Pantau suhu dan kelembapan gudang telur, buah, dan sayur secara langsung, dan terima peringatan begitu ada yang keluar batas.</p>
            </div>
        </div>
    </div>
</body>
</html>
