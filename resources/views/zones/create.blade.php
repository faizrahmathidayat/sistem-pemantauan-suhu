@extends('layouts.app')

@section('title', 'Tambah Zona')

@section('content')
    @php
        // Halaman induk yang boleh dibuka user ini: mengubah gudang butuh warehouse.manage, daftar zona butuh zone.view.
        $crumb = auth()->user()->can('warehouse.manage')
            ? ['label' => 'Gudang', 'url' => route('warehouses.edit', $warehouse)]
            : (auth()->user()->can('zone.view')
                ? ['label' => 'Batas Zona', 'url' => route('zones.index')]
                : ['label' => 'Beranda', 'url' => route('home')]);
    @endphp

    <x-page-header :title="'Tambah zona di '.$warehouse->name"
                   description="Satu zona = satu sensor ESP32. Token sensor dibuat otomatis dan ditampilkan sekali setelah zona disimpan."
                   :crumbs="[$crumb]" />

    <x-form-errors message="Zona belum disimpan. Periksa isian berikut:" />

    <form method="POST" action="{{ route('warehouses.zones.store', $warehouse) }}" class="max-w-3xl space-y-4">
        @csrf

        <x-card title="Zona">
            <div class="space-y-4">
                <div>
                    <label for="zone-name" class="form-label">Nama zona</label>
                    <input id="zone-name" type="text" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="off" class="form-input" placeholder="mis. Zona FG">
                </div>
                <div>
                    <label for="zone-device" class="form-label">Device ID sensor</label>
                    <input id="zone-device" type="text" name="device_id" value="{{ old('device_id') }}" required maxlength="64" autocomplete="off" class="form-input" placeholder="mis. cikokol-fg">
                    <p class="form-hint">Huruf kecil, angka, dan tanda minus. Harus sama dengan <code>DEVICE_ID</code> di firmware. Tidak bisa diubah setelah zona dibuat.</p>
                </div>
                <div>
                    <label for="zone-products" class="form-label">Produk di zona ini (satu nama per baris, opsional)</label>
                    <textarea id="zone-products" name="products" rows="4" class="form-input" placeholder="Telur Ayam Grade A&#10;Telur Bebek">{{ old('products') }}</textarea>
                </div>
            </div>
        </x-card>

        @include('zones._limits', ['values' => ['temp_min' => 10, 'temp_max' => 20, 'warning_margin' => 2, 'humidity_min' => 60, 'humidity_max' => 85, 'humidity_warning_margin' => 5]])

        <div class="flex flex-col gap-2 sm:flex-row">
            <button type="submit" class="btn btn-primary w-full sm:w-auto">Simpan dan buat token</button>
            <a href="{{ url()->previous() }}" class="btn btn-secondary w-full sm:w-auto">Batal</a>
        </div>
    </form>
@endsection
