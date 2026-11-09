@extends('layouts.app')

@section('title', 'Ubah Batas '.$zone->name)

@section('content')
    @php
        $input = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-base';
    @endphp

    <a href="{{ route('zones.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Kembali</a>
    <h1 class="mt-1 text-lg font-semibold">Ubah batas {{ $zone->name }}</h1>
    <p class="text-sm text-slate-500">{{ $zone->product_type->label() }} · perangkat <code>{{ $zone->device_id }}</code></p>

    @if ($errors->any())
        <div role="alert" class="mt-3 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Perubahan belum disimpan. Periksa isian berikut:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('zones.update', $zone) }}" class="mt-3 space-y-4">
        @csrf
        @method('PUT')

        <fieldset class="rounded-xl bg-white p-4 shadow-sm">
            <legend class="px-1 font-semibold">🌡️ Suhu (°C)</legend>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm">
                    <span class="mb-1 block font-medium">Minimum</span>
                    <input type="number" name="temp_min" step="0.1" inputmode="decimal" required class="{{ $input }}"
                           value="{{ old('temp_min', $zone->temp_min) }}">
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block font-medium">Maksimum</span>
                    <input type="number" name="temp_max" step="0.1" inputmode="decimal" required class="{{ $input }}"
                           value="{{ old('temp_max', $zone->temp_max) }}">
                </label>
            </div>
            <label class="mt-3 block text-sm">
                <span class="mb-1 block font-medium">Margin Waspada (±°C)</span>
                <input type="number" name="warning_margin" step="0.1" min="0" inputmode="decimal" required class="{{ $input }}"
                       value="{{ old('warning_margin', $zone->warning_margin) }}">
                <span class="mt-1 block text-xs text-slate-500">Suhu dalam jarak ini dari batas = Waspada (kuning). Di luar batas = Bahaya (merah).</span>
            </label>
        </fieldset>

        <fieldset class="rounded-xl bg-white p-4 shadow-sm">
            <legend class="px-1 font-semibold">💧 Kelembapan (%)</legend>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm">
                    <span class="mb-1 block font-medium">Minimum</span>
                    <input type="number" name="humidity_min" step="0.1" min="0" max="100" inputmode="decimal" class="{{ $input }}"
                           value="{{ old('humidity_min', $zone->humidity_min) }}">
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block font-medium">Maksimum</span>
                    <input type="number" name="humidity_max" step="0.1" min="0" max="100" inputmode="decimal" class="{{ $input }}"
                           value="{{ old('humidity_max', $zone->humidity_max) }}">
                </label>
            </div>
            <p class="mt-1 text-xs text-slate-500">Kosongkan kedua kolom bila kelembapan zona ini tidak perlu diperiksa.</p>
            <label class="mt-3 block text-sm">
                <span class="mb-1 block font-medium">Margin Waspada (±%)</span>
                <input type="number" name="humidity_warning_margin" step="0.1" min="0" inputmode="decimal" required class="{{ $input }}"
                       value="{{ old('humidity_warning_margin', $zone->humidity_warning_margin) }}">
            </label>
        </fieldset>

        <div class="flex flex-col gap-2 sm:flex-row">
            <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-3 font-semibold text-white hover:bg-emerald-700">Simpan</button>
            <a href="{{ route('zones.index') }}" class="rounded-lg bg-white px-6 py-3 text-center font-medium shadow-sm hover:bg-slate-50">Batal</a>
        </div>
    </form>
@endsection
