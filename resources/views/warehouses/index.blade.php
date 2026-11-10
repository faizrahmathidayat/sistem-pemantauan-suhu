@extends('layouts.app')

@section('title', 'Gudang')

@section('content')
    <x-page-header title="Gudang" description="Tiap gudang punya zona (dengan sensor sendiri) dan user yang bertugas di sana.">
        <a href="{{ route('warehouses.create') }}" class="btn btn-primary w-full sm:w-auto"><x-icon name="plus" class="size-4" /> Gudang</a>
    </x-page-header>

    <x-flash />

    @if ($warehouses->isEmpty())
        <x-card><x-empty title="Belum ada gudang yang bisa Anda kelola." icon="building" /></x-card>
    @else
        {{-- Layar lebar: tabel --}}
        <x-card flush class="hidden lg:block">
            <x-table>
                <thead class="border-b border-gray-100">
                    <tr>
                        <th class="table-th">Gudang</th>
                        <th class="table-th">Zona</th>
                        <th class="table-th">User ditugaskan</th>
                        <th class="table-th text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($warehouses as $warehouse)
                        <tr>
                            <td class="table-td font-medium text-gray-800">{{ $warehouse->name }}</td>
                            <td class="table-td">{{ $warehouse->zones_count }} zona</td>
                            <td class="table-td">{{ $warehouse->users_count }} user</td>
                            <td class="table-td">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('warehouses.edit', $warehouse) }}" class="btn btn-secondary btn-sm">Ubah</a>
                                    <form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}" onsubmit="return confirm('Hapus gudang ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>

        {{-- HP dan layar sedang: daftar kartu --}}
        <ul data-mobile-list class="grid gap-3 md:grid-cols-2 lg:hidden">
            @foreach ($warehouses as $warehouse)
                <li class="rounded-2xl border border-gray-200 bg-white p-4">
                    <h2 class="truncate text-base font-semibold text-gray-800">{{ $warehouse->name }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ $warehouse->zones_count }} zona · {{ $warehouse->users_count }} user ditugaskan</p>
                    <div class="mt-4 flex gap-2">
                        <a href="{{ route('warehouses.edit', $warehouse) }}" class="btn btn-secondary btn-sm flex-1">Ubah</a>
                        <form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}" class="flex-1" onsubmit="return confirm('Hapus gudang ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm w-full">Hapus</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
