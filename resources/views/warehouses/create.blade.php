@extends('layouts.app')

@section('title', 'Tambah Gudang')

@section('content')
    <a href="{{ route('warehouses.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Kembali</a>
    <h1 class="mt-1 text-lg font-semibold">Tambah gudang</h1>

    @include('warehouses._form', ['action' => route('warehouses.store')])
@endsection
