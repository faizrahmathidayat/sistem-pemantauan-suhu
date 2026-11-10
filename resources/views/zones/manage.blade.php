@extends('layouts.app')

@section('title', 'Kelola Zona '.$zone->name)

@section('content')
    @php
        $newToken = session('new_token');
        $crumb = auth()->user()->can('zone.view')
            ? ['label' => 'Batas Zona', 'url' => route('zones.index')]
            : ['label' => 'Beranda', 'url' => route('home')];
    @endphp

    <x-page-header :title="'Kelola zona '.$zone->name" :crumbs="[$crumb]" />
    <p class="-mt-4 mb-6 text-sm text-gray-500">{{ $zone->warehouse->name }} · sensor <code>{{ $zone->device_id }}</code></p>

    <x-flash />

    @if ($newToken)
        <section class="mb-4 max-w-3xl rounded-2xl border-2 border-warning-500 bg-warning-50 p-5">
            <h2 class="text-base font-semibold text-gray-800">Token sensor (hanya tampil sekali)</h2>
            <p class="mt-1 text-sm text-gray-700">Salin sekarang ke <code>config.h</code> firmware (<code>DEVICE_TOKEN</code>). Setelah halaman ini ditutup, token tidak bisa dilihat lagi; bila hilang, buat token baru.</p>
            <p class="mt-3 rounded-lg bg-white px-3 py-2 font-mono text-sm break-all select-all">{{ $newToken }}</p>
        </section>
    @endif

    <x-form-errors />

    <div class="max-w-3xl space-y-4">
        <x-card title="Nama dan produk">
            <form method="POST" action="{{ route('zones.manage.update', $zone) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="manage-name" class="form-label">Nama zona</label>
                    <input id="manage-name" type="text" name="name" value="{{ old('name', $zone->name) }}" required maxlength="100" autocomplete="off" class="form-input">
                </div>
                <div>
                    <label for="manage-products" class="form-label">Produk di zona ini (satu nama per baris)</label>
                    <textarea id="manage-products" name="products" rows="5" class="form-input">{{ old('products', $zone->products->pluck('name')->implode("\n")) }}</textarea>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <button type="submit" class="btn btn-primary w-full sm:w-auto">Simpan</button>
                    @can('zone.update')
                        <a href="{{ route('zones.edit', $zone) }}" class="btn btn-secondary w-full sm:w-auto">Ubah batas suhu/kelembapan</a>
                    @endcan
                </div>
            </form>
        </x-card>

        <x-card title="Token sensor" data-token-section>
            <p class="text-sm text-gray-600">
                {{ $zone->device_token_hash ? 'Token sudah dibuat.' : 'Belum ada token.' }}
                Membuat token baru membuat token lama langsung tidak berlaku; firmware harus diperbarui dengan token baru.
            </p>
            <form method="POST" action="{{ route('zones.token', $zone) }}" class="mt-4"
                  onsubmit="return confirm('Buat token baru? Token lama langsung tidak berlaku.')">
                @csrf
                <button type="submit" class="btn btn-secondary w-full sm:w-auto"><x-icon name="key" class="size-4" /> Buat token baru</button>
            </form>
        </x-card>

        <section data-danger-zone class="rounded-2xl border border-error-100 bg-white p-5 sm:p-6">
            <h2 class="text-base font-medium text-error-700">Hapus zona</h2>
            <p class="mt-1 text-sm text-gray-600">Seluruh pembacaan sensor, insiden, dan produk zona ini ikut terhapus dan tidak bisa dikembalikan. Sensor yang masih mengirim data akan ditolak.</p>
            <form method="POST" action="{{ route('zones.destroy', $zone) }}" class="mt-4"
                  onsubmit="return confirm('Hapus zona ini beserta SEMUA datanya? Tindakan ini tidak bisa dibatalkan.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger w-full sm:w-auto"><x-icon name="trash" class="size-4" /> Hapus zona</button>
            </form>
        </section>
    </div>
@endsection
