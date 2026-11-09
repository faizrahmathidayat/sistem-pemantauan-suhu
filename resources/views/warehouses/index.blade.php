@extends('layouts.app')

@section('title', 'Gudang')

@section('content')
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold">Gudang</h1>
            <p class="text-sm text-slate-500">Tiap gudang punya zona (dengan sensor sendiri) dan user yang bertugas di sana.</p>
        </div>
        <a href="{{ route('warehouses.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Gudang</a>
    </div>

    @if (session('status'))
        <p role="status" class="mt-3 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">✅ {{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p role="alert" class="mt-3 rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ session('error') }}</p>
    @endif

    <ul class="mt-3 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($warehouses as $warehouse)
            <li class="rounded-xl bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="truncate font-semibold">{{ $warehouse->name }}</h2>
                        <p class="text-sm text-slate-500">{{ $warehouse->zones_count }} zona · {{ $warehouse->users_count }} user ditugaskan</p>
                    </div>
                    <a href="{{ route('warehouses.edit', $warehouse) }}" class="rounded-lg bg-white px-3 py-2 text-sm font-medium shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">Ubah</a>
                </div>
                <form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}" class="mt-3"
                      onsubmit="return confirm('Hapus gudang ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm text-red-700 underline">Hapus</button>
                </form>
            </li>
        @empty
            <li class="rounded-xl bg-white p-4 text-slate-500 shadow-sm">Belum ada gudang yang bisa Anda kelola.</li>
        @endforelse
    </ul>
@endsection
