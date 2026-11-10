@extends('layouts.app')

@section('title', 'Tambah Gudang')

@section('content')
    <x-page-header title="Tambah gudang" :crumbs="[['label' => 'Gudang', 'url' => route('warehouses.index')]]" />

    @include('warehouses._form', ['action' => route('warehouses.store')])
@endsection
