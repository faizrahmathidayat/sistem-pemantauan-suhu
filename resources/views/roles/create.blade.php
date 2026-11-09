@extends('layouts.app')

@section('title', 'Tambah Role')

@section('content')
    <a href="{{ route('roles.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Kembali</a>
    <h1 class="mt-1 text-lg font-semibold">Tambah role</h1>

    @include('roles._form', ['action' => route('roles.store')])
@endsection
