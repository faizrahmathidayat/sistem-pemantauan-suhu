@extends('layouts.app')

@section('title', 'Kelola Zona '.$zone->name)

@section('content')
    @php
        $input = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-base';
        $newToken = session('new_token');
    @endphp

    @can('zone.view')
        <a href="{{ route('zones.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Batas Zona</a>
    @else
        <a href="{{ route('home') }}" class="text-sm text-slate-500 hover:text-slate-800">← Beranda</a>
    @endcan
    <h1 class="mt-1 text-lg font-semibold">Kelola zona {{ $zone->name }}</h1>
    <p class="text-sm text-slate-500">{{ $zone->warehouse->name }} · sensor <code>{{ $zone->device_id }}</code></p>

    @if (session('status'))
        <p role="status" class="mt-3 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">✅ {{ session('status') }}</p>
    @endif

    @if ($newToken)
        <section class="mt-3 rounded-xl border-2 border-amber-400 bg-amber-50 p-4">
            <h2 class="font-semibold">Token sensor (hanya tampil sekali)</h2>
            <p class="mt-1 text-sm text-slate-700">Salin sekarang ke <code>config.h</code> firmware (<code>DEVICE_TOKEN</code>). Setelah halaman ini ditutup, token tidak bisa dilihat lagi; bila hilang, buat token baru.</p>
            <p class="mt-2 break-all rounded-lg bg-white px-3 py-2 font-mono text-sm select-all">{{ $newToken }}</p>
        </section>
    @endif

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

    <form method="POST" action="{{ route('zones.manage.update', $zone) }}" class="mt-3 space-y-3 rounded-xl bg-white p-4 shadow-sm">
        @csrf
        @method('PUT')
        <label class="block text-sm">
            <span class="mb-1 block font-medium">Nama zona</span>
            <input type="text" name="name" value="{{ old('name', $zone->name) }}" required maxlength="100" autocomplete="off" class="{{ $input }}">
        </label>
        <label class="block text-sm">
            <span class="mb-1 block font-medium">Produk di zona ini (satu nama per baris)</span>
            <textarea name="products" rows="5" class="{{ $input }}">{{ old('products', $zone->products->pluck('name')->implode("\n")) }}</textarea>
        </label>
        <div class="flex flex-col gap-2 pt-1 sm:flex-row">
            <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-3 font-semibold text-white hover:bg-emerald-700">Simpan</button>
            @can('zone.update')
                <a href="{{ route('zones.edit', $zone) }}" class="rounded-lg bg-white px-6 py-3 text-center font-medium shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">Ubah batas suhu/kelembapan</a>
            @endcan
        </div>
    </form>

    <section class="mt-4 rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Token sensor</h2>
        <p class="mt-1 text-sm text-slate-600">
            {{ $zone->device_token_hash ? 'Token sudah dibuat.' : 'Belum ada token.' }}
            Membuat token baru membuat token lama langsung tidak berlaku; firmware harus diperbarui dengan token baru.
        </p>
        <form method="POST" action="{{ route('zones.token', $zone) }}" class="mt-3"
              onsubmit="return confirm('Buat token baru? Token lama langsung tidak berlaku.')">
            @csrf
            <button type="submit" class="rounded-lg bg-white px-4 py-2 text-sm font-medium shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">Buat token baru</button>
        </form>
    </section>

    <section class="mt-4 rounded-xl border border-red-200 bg-white p-4">
        <h2 class="font-semibold text-red-800">Hapus zona</h2>
        <p class="mt-1 text-sm text-slate-600">Seluruh pembacaan sensor, insiden, dan produk zona ini ikut terhapus dan tidak bisa dikembalikan. Sensor yang masih mengirim data akan ditolak.</p>
        <form method="POST" action="{{ route('zones.destroy', $zone) }}" class="mt-3"
              onsubmit="return confirm('Hapus zona ini beserta SEMUA datanya? Tindakan ini tidak bisa dibatalkan.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Hapus zona</button>
        </form>
    </section>
@endsection
