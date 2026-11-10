@extends('layouts.app')

@section('title', 'Ubah Gudang '.$warehouse->name)

@section('content')
    <x-page-header :title="'Ubah gudang '.$warehouse->name" :crumbs="[['label' => 'Gudang', 'url' => route('warehouses.index')]]" />

    @include('warehouses._form', ['action' => route('warehouses.update', $warehouse)])

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
