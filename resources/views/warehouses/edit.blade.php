@extends('layouts.app')

@section('title', 'Ubah Gudang '.$warehouse->name)

@section('content')
    <x-page-header :title="'Ubah gudang '.$warehouse->name" :crumbs="[['label' => 'Gudang', 'url' => route('warehouses.index')]]" />

    <x-flash />

    @include('warehouses._form', ['action' => route('warehouses.update', $warehouse)])

    @can('warehouse.manage')
        @php($monitorUrl = $warehouse->monitorUrl())
        <x-card title="Layar monitor" class="mt-6 max-w-2xl" data-monitor-section>
            <p class="text-sm text-gray-600">
                Halaman layar penuh untuk TV di gudang ini, bisa dilihat semua karyawan tanpa login. Siapa pun yang memegang alamatnya bisa melihat suhu dan kelembapan gudang, jadi jangan dibagikan di luar.
            </p>
            <p class="mt-2 text-sm font-medium {{ $warehouse->hasMonitorToken() ? 'text-success-700' : 'text-gray-500' }}">
                {{ $warehouse->hasMonitorToken() ? 'Alamat monitor aktif.' : 'Belum ada alamat monitor.' }}
            </p>

            @if ($monitorUrl)
                <div data-monitor-url class="mt-3"
                     x-data="{ copied: false, async copy() { try { await navigator.clipboard.writeText(this.$refs.url.textContent.trim()); this.copied = true; setTimeout(() => this.copied = false, 2000); } catch (e) { const range = document.createRange(); range.selectNodeContents(this.$refs.url); getSelection().removeAllRanges(); getSelection().addRange(range); } } }">
                    <p class="text-sm text-gray-700">Buka alamat ini di browser TV lalu jadikan layar penuh.</p>
                    <p x-ref="url" class="mt-2 rounded-lg bg-gray-50 px-3 py-2 font-mono text-sm break-all select-all">{{ $monitorUrl }}</p>
                    <button type="button" class="btn btn-secondary btn-sm mt-2" @click="copy()"><span x-text="copied ? 'Tersalin' : 'Salin alamat'">Salin alamat</span></button>
                </div>
            @elseif ($warehouse->hasMonitorToken())
                <p class="mt-2 text-sm text-gray-600">Alamat ini dibuat sebelum alamat bisa ditampilkan lagi. Buat ulang alamat untuk melihatnya (alamat lama langsung mati).</p>
            @endif

            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                <form method="POST" action="{{ route('warehouses.monitor.store', $warehouse) }}"
                      @if ($warehouse->hasMonitorToken()) onsubmit="return confirm('Buat alamat baru? Alamat lama langsung mati dan TV harus diisi alamat baru.')" @endif>
                    @csrf
                    <button type="submit" class="btn btn-secondary w-full sm:w-auto"><x-icon name="key" class="size-4" /> {{ $warehouse->hasMonitorToken() ? 'Buat ulang alamat' : 'Buat alamat monitor' }}</button>
                </form>

                @if ($warehouse->hasMonitorToken())
                    <form method="POST" action="{{ route('warehouses.monitor.destroy', $warehouse) }}"
                          onsubmit="return confirm('Matikan alamat monitor? TV yang memakainya langsung berhenti menampilkan data.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-full sm:w-auto">Matikan alamat</button>
                    </form>
                @endif
            </div>
        </x-card>
    @endcan

    <x-card title="Zona di gudang ini" flush class="mt-6 max-w-2xl">
        <x-slot:actions>
            @can('zone.manage')
                <a href="{{ route('warehouses.zones.create', $warehouse) }}" class="btn btn-primary btn-sm">+ Zona</a>
            @endcan
        </x-slot:actions>

        <ul class="divide-y divide-gray-100">
            @forelse ($warehouse->zones as $zone)
                <li class="flex items-start justify-between gap-3 px-5 py-4 sm:px-6">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-800">{{ $zone->name }}</p>
                        <p class="text-xs text-gray-500">{{ $zone->device_id }}@if ($zone->products->isNotEmpty()) · {{ $zone->products->pluck('name')->implode(', ') }}@endif</p>
                    </div>
                    @can('zone.manage')
                        <a href="{{ route('zones.manage', $zone) }}" class="btn btn-secondary btn-sm shrink-0">Kelola</a>
                    @endcan
                </li>
            @empty
                <li><x-empty title="Belum ada zona." icon="zone" /></li>
            @endforelse
        </ul>
    </x-card>
@endsection
