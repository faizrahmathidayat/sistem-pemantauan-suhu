@extends('layouts.app')

@section('title', 'Belum ada akses')

@section('content')
    <x-card class="mx-auto mt-8 max-w-md">
        <x-empty title="Akun Anda belum punya hak akses" icon="shield">
            Hubungi administrator agar akun Anda diberi role. Setelah itu muat ulang halaman ini.
        </x-empty>
    </x-card>
@endsection
