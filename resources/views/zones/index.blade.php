@extends('layouts.app')

@section('title', 'Batas Zona')

@section('content')
    @php
        $n = fn ($v) => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    @endphp

    <h1 class="text-lg font-semibold">Batas Suhu &amp; Kelembapan</h1>
    <p class="text-sm text-slate-500">Di luar batas = status Bahaya. Dekat batas (dalam margin) = status Waspada. Perubahan langsung dipakai pada pembacaan sensor berikutnya.</p>

    @if (session('status'))
        <p role="status" class="mt-3 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">✅ {{ session('status') }}</p>
    @endif

    @forelse ($zones->groupBy(fn ($zone) => $zone->warehouse->name) as $warehouseName => $warehouseZones)
        <h2 class="mt-5 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $warehouseName }}</h2>
        <ul class="mt-2 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($warehouseZones as $zone)
                <li class="rounded-xl bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="font-semibold">{{ $zone->name }}</h3>
                            <p class="text-xs text-slate-500">{{ $zone->device_id }}</p>
                            @if ($zone->products->isNotEmpty())
                                <p class="text-xs text-slate-500">{{ $zone->products->pluck('name')->implode(', ') }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            @can('zone.update')
                                <a href="{{ route('zones.edit', $zone) }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Ubah batas</a>
                            @endcan
                            @can('zone.manage')
                                <a href="{{ route('zones.manage', $zone) }}" class="text-xs text-slate-600 underline">Kelola zona</a>
                            @endcan
                        </div>
                    </div>
                    <dl class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">🌡️ Suhu</dt>
                            <dd class="font-medium">{{ $n($zone->temp_min) }}–{{ $n($zone->temp_max) }} °C <span class="text-xs font-normal text-slate-500">(Waspada ±{{ $n($zone->warning_margin) }})</span></dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">💧 Kelembapan</dt>
                            <dd class="font-medium">
                                @if ($zone->humidity_min !== null && $zone->humidity_max !== null)
                                    {{ $n($zone->humidity_min) }}–{{ $n($zone->humidity_max) }} % <span class="text-xs font-normal text-slate-500">(Waspada ±{{ $n($zone->humidity_warning_margin) }})</span>
                                @else
                                    <span class="text-slate-500">Tidak diperiksa</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </li>
            @endforeach
        </ul>
    @empty
        <p class="mt-3 rounded-xl bg-white p-4 text-slate-500 shadow-sm">Belum ada zona yang bisa Anda lihat.</p>
    @endforelse
@endsection
