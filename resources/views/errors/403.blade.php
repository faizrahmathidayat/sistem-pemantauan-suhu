@extends('layouts.app')

@section('title', 'Tidak diizinkan')

@section('content')
    <x-card class="mx-auto mt-8 max-w-md">
        <x-empty title="Anda tidak punya izin untuk membuka halaman ini" icon="shield">
            Jika ini keliru, hubungi administrator.
        </x-empty>
        <a href="{{ route('home') }}" class="btn btn-primary w-full">Kembali</a>
    </x-card>
@endsection
