@extends('layouts.app')

@section('title', 'Tambah User')

@section('content')
    <a href="{{ route('users.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Kembali</a>
    <h1 class="mt-1 text-lg font-semibold">Tambah user</h1>

    @include('users._form', ['action' => route('users.store')])
@endsection
