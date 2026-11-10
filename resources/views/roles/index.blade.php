@extends('layouts.app')

@section('title', 'Role')

@section('content')
    @php
        $summary = function ($role) {
            if ($role->is_system) {
                return 'Semua izin (selalu, termasuk izin baru).';
            }
            $permissions = $role->permissions();

            return count($permissions).' dari '.count(\App\Enums\Permission::cases()).' izin: '
                .($permissions === [] ? 'tidak ada' : implode(', ', array_map(fn ($p) => $p->label(), $permissions)));
        };
    @endphp

    <x-page-header title="Role & Hak Akses" description="Role menentukan fitur apa yang boleh dipakai user.">
        <a href="{{ route('roles.create') }}" class="btn btn-primary w-full sm:w-auto"><x-icon name="plus" class="size-4" /> Role</a>
    </x-page-header>

    <x-flash />

    {{-- Layar lebar: tabel --}}
    <x-card flush class="hidden lg:block">
        <x-table>
            <thead class="border-b border-gray-100">
                <tr>
                    <th class="table-th">Role</th>
                    <th class="table-th">User</th>
                    <th class="table-th">Izin</th>
                    <th class="table-th text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($roles as $role)
                    <tr class="align-top">
                        <td class="table-td font-medium text-gray-800">
                            {{ $role->name }}
                            @if ($role->is_system) <x-badge variant="brand" class="ml-1">Sistem</x-badge> @endif
                        </td>
                        <td class="table-td whitespace-nowrap">{{ $role->users_count }} user</td>
                        <td class="table-td max-w-xl">{{ $summary($role) }}</td>
                        <td class="table-td">
                            @unless ($role->is_system)
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('roles.edit', $role) }}" class="btn btn-secondary btn-sm">Ubah</a>
                                    <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Hapus role {{ e($role->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                </div>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    </x-card>

    {{-- HP dan layar sedang: daftar kartu --}}
    <ul data-mobile-list class="grid gap-3 md:grid-cols-2 lg:hidden">
        @foreach ($roles as $role)
            <li class="rounded-2xl border border-gray-200 bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-800">{{ $role->name }}</h2>
                        <p class="text-sm text-gray-500">{{ $role->users_count }} user</p>
                    </div>
                    @if ($role->is_system) <x-badge variant="brand">Sistem</x-badge> @endif
                </div>
                <p class="mt-2 text-sm text-gray-600">{{ $summary($role) }}</p>
                @unless ($role->is_system)
                    <div class="mt-4 flex gap-2">
                        <a href="{{ route('roles.edit', $role) }}" class="btn btn-secondary btn-sm flex-1">Ubah</a>
                        <form method="POST" action="{{ route('roles.destroy', $role) }}" class="flex-1" onsubmit="return confirm('Hapus role {{ e($role->name) }}?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm w-full">Hapus</button>
                        </form>
                    </div>
                @endunless
            </li>
        @endforeach
    </ul>
@endsection
