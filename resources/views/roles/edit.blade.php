@extends('layouts.app')

@section('title', 'Ubah Role '.$role->name)

@section('content')
    <a href="{{ route('roles.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Kembali</a>
    <h1 class="mt-1 text-lg font-semibold">Ubah role {{ $role->name }}</h1>

    @include('roles._form', ['action' => route('roles.update', $role)])
@endsection
