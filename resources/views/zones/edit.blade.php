@extends('layouts.app')

@section('title', 'Ubah Batas '.$zone->name)

@section('content')
    <x-page-header :title="'Ubah batas '.$zone->name"
                   :crumbs="[['label' => 'Batas Zona', 'url' => route('zones.index')]]">
    </x-page-header>
    <p class="-mt-4 mb-6 text-sm text-gray-500">
        {{ $zone->warehouse->name }} · perangkat <code>{{ $zone->device_id }}</code>
        @if ($zone->products->isNotEmpty())
            <br>Produk: {{ $zone->products->pluck('name')->implode(', ') }}
        @endif
    </p>

    <x-form-errors />

    <form method="POST" action="{{ route('zones.update', $zone) }}" class="max-w-3xl space-y-4">
        @csrf
        @method('PUT')

        @include('zones._limits', ['values' => [
            'temp_min' => $zone->temp_min, 'temp_max' => $zone->temp_max, 'warning_margin' => $zone->warning_margin,
            'humidity_min' => $zone->humidity_min, 'humidity_max' => $zone->humidity_max, 'humidity_warning_margin' => $zone->humidity_warning_margin,
        ]])

        <div class="flex flex-col gap-2 sm:flex-row">
            <button type="submit" class="btn btn-primary w-full sm:w-auto">Simpan</button>
            <a href="{{ route('zones.index') }}" class="btn btn-secondary w-full sm:w-auto">Batal</a>
        </div>
    </form>
@endsection
