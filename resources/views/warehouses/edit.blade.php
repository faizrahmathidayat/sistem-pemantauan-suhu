@extends('layouts.app')

@section('title', 'Ubah Gudang '.$warehouse->name)

@section('content')
    <a href="{{ route('warehouses.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Kembali</a>
    <h1 class="mt-1 text-lg font-semibold">Ubah gudang {{ $warehouse->name }}</h1>

    @include('warehouses._form', ['action' => route('warehouses.update', $warehouse)])

    <section class="mt-5">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Zona di gudang ini</h2>
            @can('zone.manage')
                <a href="{{ route('warehouses.zones.create', $warehouse) }}" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Zona</a>
            @endcan
        </div>
        <ul class="mt-2 space-y-2">
            @forelse ($warehouse->zones as $zone)
                <li class="flex items-start justify-between gap-2 rounded-xl bg-white p-3 shadow-sm">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $zone->name }}</p>
                        <p class="text-xs text-slate-500">{{ $zone->device_id }}@if ($zone->products->isNotEmpty()) · {{ $zone->products->pluck('name')->implode(', ') }}@endif</p>
                    </div>
                    @can('zone.manage')
                        <a href="{{ route('zones.manage', $zone) }}" class="shrink-0 rounded-lg bg-white px-3 py-2 text-sm font-medium shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">Kelola</a>
                    @endcan
                </li>
            @empty
                <li class="rounded-xl bg-white p-3 text-sm text-slate-500 shadow-sm">Belum ada zona.</li>
            @endforelse
        </ul>
    </section>
@endsection
