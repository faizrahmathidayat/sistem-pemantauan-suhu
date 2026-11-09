@extends('layouts.app')

@section('title', 'Tidak diizinkan')

@section('content')
    <div class="mx-auto mt-8 max-w-md rounded-xl bg-white p-6 text-center shadow-sm">
        <p class="text-3xl" aria-hidden="true">⛔</p>
        <h1 class="mt-2 text-lg font-semibold">Anda tidak punya izin untuk membuka halaman ini</h1>
        <p class="mt-1 text-sm text-slate-600">Jika ini keliru, hubungi administrator.</p>
        <a href="{{ route('home') }}" class="mt-4 inline-block rounded-lg bg-emerald-600 px-5 py-2.5 font-semibold text-white hover:bg-emerald-700">Kembali</a>
    </div>
@endsection
