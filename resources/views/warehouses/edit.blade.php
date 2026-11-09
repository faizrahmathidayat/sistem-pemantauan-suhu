@extends('layouts.app')

@section('title', 'Ubah Gudang '.$warehouse->name)

@section('content')
    <a href="{{ route('warehouses.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Kembali</a>
    <h1 class="mt-1 text-lg font-semibold">Ubah gudang {{ $warehouse->name }}</h1>

    @include('warehouses._form', ['action' => route('warehouses.update', $warehouse)])

    <section class="mt-5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Zona di gudang ini</h2>
        <ul class="mt-2 space-y-2">
            @forelse ($warehouse->zones as $zone)
                <li class="rounded-xl bg-white p-3 shadow-sm">
                    <p class="font-semibold">{{ $zone->name }}</p>
                    <p class="text-xs text-slate-500">{{ $zone->device_id }}@if ($zone->products->isNotEmpty()) · {{ $zone->products->pluck('name')->implode(', ') }}@endif</p>
                </li>
            @empty
                <li class="rounded-xl bg-white p-3 text-sm text-slate-500 shadow-sm">Belum ada zona.</li>
            @endforelse
        </ul>
    </section>
@endsection
