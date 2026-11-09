@extends('layouts.app')

@section('title', 'Tambah Zona')

@section('content')
    @php
        $input = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-base';
    @endphp

    <a href="{{ url()->previous() }}" class="text-sm text-slate-500 hover:text-slate-800">← Kembali</a>
    <h1 class="mt-1 text-lg font-semibold">Tambah zona di {{ $warehouse->name }}</h1>
    <p class="text-sm text-slate-500">Satu zona = satu sensor ESP32. Token sensor dibuat otomatis dan ditampilkan sekali setelah zona disimpan.</p>

    @if ($errors->any())
        <div role="alert" class="mt-3 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Zona belum disimpan. Periksa isian berikut:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('warehouses.zones.store', $warehouse) }}" class="mt-3 space-y-4">
        @csrf

        <fieldset class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
            <legend class="px-1 font-semibold">Zona</legend>
            <label class="block text-sm">
                <span class="mb-1 block font-medium">Nama zona</span>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="off" class="{{ $input }}" placeholder="mis. Zona FG">
            </label>
            <label class="block text-sm">
                <span class="mb-1 block font-medium">Device ID sensor</span>
                <input type="text" name="device_id" value="{{ old('device_id') }}" required maxlength="64" autocomplete="off" class="{{ $input }}" placeholder="mis. cikokol-fg">
                <span class="mt-1 block text-xs text-slate-500">Huruf kecil, angka, dan tanda minus. Harus sama dengan <code>DEVICE_ID</code> di firmware. Tidak bisa diubah setelah zona dibuat.</span>
            </label>
            <label class="block text-sm">
                <span class="mb-1 block font-medium">Produk di zona ini (satu nama per baris, opsional)</span>
                <textarea name="products" rows="4" class="{{ $input }}" placeholder="Telur Ayam Grade A&#10;Telur Bebek">{{ old('products') }}</textarea>
            </label>
        </fieldset>

        <fieldset class="rounded-xl bg-white p-4 shadow-sm">
            <legend class="px-1 font-semibold">🌡️ Suhu (°C)</legend>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm"><span class="mb-1 block font-medium">Minimum</span>
                    <input type="number" name="temp_min" step="0.1" inputmode="decimal" required class="{{ $input }}" value="{{ old('temp_min', 10) }}"></label>
                <label class="block text-sm"><span class="mb-1 block font-medium">Maksimum</span>
                    <input type="number" name="temp_max" step="0.1" inputmode="decimal" required class="{{ $input }}" value="{{ old('temp_max', 20) }}"></label>
            </div>
            <label class="mt-3 block text-sm"><span class="mb-1 block font-medium">Margin Waspada (±°C)</span>
                <input type="number" name="warning_margin" step="0.1" min="0" inputmode="decimal" required class="{{ $input }}" value="{{ old('warning_margin', 2) }}"></label>
        </fieldset>

        <fieldset class="rounded-xl bg-white p-4 shadow-sm">
            <legend class="px-1 font-semibold">💧 Kelembapan (%)</legend>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm"><span class="mb-1 block font-medium">Minimum</span>
                    <input type="number" name="humidity_min" step="0.1" min="0" max="100" inputmode="decimal" class="{{ $input }}" value="{{ old('humidity_min', 60) }}"></label>
                <label class="block text-sm"><span class="mb-1 block font-medium">Maksimum</span>
                    <input type="number" name="humidity_max" step="0.1" min="0" max="100" inputmode="decimal" class="{{ $input }}" value="{{ old('humidity_max', 85) }}"></label>
            </div>
            <p class="mt-1 text-xs text-slate-500">Kosongkan kedua kolom bila kelembapan zona ini tidak perlu diperiksa.</p>
            <label class="mt-3 block text-sm"><span class="mb-1 block font-medium">Margin Waspada (±%)</span>
                <input type="number" name="humidity_warning_margin" step="0.1" min="0" inputmode="decimal" required class="{{ $input }}" value="{{ old('humidity_warning_margin', 5) }}"></label>
        </fieldset>

        <div class="flex flex-col gap-2 sm:flex-row">
            <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-3 font-semibold text-white hover:bg-emerald-700">Simpan dan buat token</button>
            <a href="{{ url()->previous() }}" class="rounded-lg bg-white px-6 py-3 text-center font-medium shadow-sm hover:bg-slate-50">Batal</a>
        </div>
    </form>
@endsection
