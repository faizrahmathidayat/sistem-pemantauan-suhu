@extends('layouts.app')

@section('title', 'Batas Zona')

@section('content')
    @php
        $n = fn ($v) => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    @endphp

    <x-page-header title="Batas Suhu & Kelembapan" description="Di luar batas = status Bahaya. Dekat batas (dalam margin) = status Waspada. Perubahan langsung dipakai pada pembacaan sensor berikutnya." />

    @if (session('status'))
        <x-alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @forelse ($warehouses as $warehouse)
        {{-- Tiap gudang bisa dilipat; pilihan diingat per peramban (localStorage). Tanpa JS semuanya tetap terbuka. --}}
        <section class="mt-6 first:mt-0"
                 x-data="{
                     open: true,
                     init() { try { this.open = ! JSON.parse(localStorage.getItem('zones-collapsed') || '[]').includes({{ $warehouse->id }}); } catch (e) {} },
                     toggle() {
                         this.open = ! this.open;
                         try {
                             const ids = new Set(JSON.parse(localStorage.getItem('zones-collapsed') || '[]'));
                             this.open ? ids.delete({{ $warehouse->id }}) : ids.add({{ $warehouse->id }});
                             localStorage.setItem('zones-collapsed', JSON.stringify([...ids]));
                         } catch (e) {}
                     },
                 }">
        <div class="mb-3 flex items-center justify-between gap-3">
            <button type="button" data-warehouse-toggle @click="toggle()" :aria-expanded="open"
                    class="-ml-2 flex min-w-0 items-center gap-2 rounded-lg px-2 py-1 text-left hover:bg-gray-100">
                <x-icon name="chevron-down" class="size-4 shrink-0 text-gray-500 transition-transform" ::class="open ? '' : '-rotate-90'" />
                <h2 class="truncate text-sm font-semibold tracking-wide text-gray-500 uppercase">{{ $warehouse->name }}</h2>
                <span class="shrink-0 text-xs font-normal text-gray-400">{{ $warehouse->zones->count() }} zona</span>
            </button>
            @can('zone.manage')
                <a href="{{ route('warehouses.zones.create', $warehouse) }}" class="btn btn-secondary btn-sm">+ Zona</a>
            @endcan
        </div>
        <ul x-show="open" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($warehouse->zones as $zone)
                <li>
                    <x-card class="h-full">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-semibold text-gray-800">{{ $zone->name }}</h3>
                                <p class="truncate text-xs text-gray-500">{{ $zone->device_id }}</p>
                            </div>
                        </div>
                        @if ($zone->products->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($zone->products as $product)
                                    <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ $product->name }}</span>
                                @endforeach
                            </div>
                        @endif
                        <dl class="mt-4 space-y-2 rounded-xl bg-gray-50 p-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <dt class="flex items-center gap-2 text-gray-500"><x-icon name="thermometer" class="size-4" /> Suhu</dt>
                                <dd class="text-right font-medium text-gray-800">{{ $n($zone->temp_min) }}–{{ $n($zone->temp_max) }} °C <span class="block text-xs font-normal text-gray-500 sm:inline">(Waspada ±{{ $n($zone->warning_margin) }})</span></dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="flex items-center gap-2 text-gray-500"><x-icon name="droplet" class="size-4" /> Kelembapan</dt>
                                <dd class="text-right font-medium text-gray-800">
                                    @if ($zone->humidity_min !== null && $zone->humidity_max !== null)
                                        {{ $n($zone->humidity_min) }}–{{ $n($zone->humidity_max) }} % <span class="block text-xs font-normal text-gray-500 sm:inline">(Waspada ±{{ $n($zone->humidity_warning_margin) }})</span>
                                    @else
                                        <span class="font-normal text-gray-500">Tidak diperiksa</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                            @can('zone.update')
                                <a href="{{ route('zones.edit', $zone) }}" class="btn btn-primary w-full sm:flex-1">Ubah batas</a>
                            @endcan
                            @can('zone.manage')
                                <a href="{{ route('zones.manage', $zone) }}" class="btn btn-secondary w-full sm:flex-1">Kelola zona</a>
                            @endcan
                        </div>
                    </x-card>
                </li>
            @empty
                <li class="md:col-span-2 xl:col-span-3">
                    <x-card><x-empty title="Belum ada zona di gudang ini." icon="zone" /></x-card>
                </li>
            @endforelse
        </ul>
        </section>
    @empty
        <x-card><x-empty title="Belum ada gudang yang bisa Anda lihat." icon="building" /></x-card>
    @endforelse
@endsection
