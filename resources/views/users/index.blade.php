@extends('layouts.app')

@section('title', 'User')

@section('content')
    @php
        $me = auth()->user();
        $canTouch = fn ($user) => $me->isAdministrator() || ! $user->isAdministrator();
        $canDelete = fn ($user) => ! $user->is($me) && $canTouch($user);
        $warehouseNames = function ($user) use ($visibleWarehouseIds) {
            if ($user->isAdministrator()) {
                return 'Semua gudang';
            }
            $names = $user->warehouses->filter(fn ($w) => $visibleWarehouseIds === null || in_array($w->id, $visibleWarehouseIds, true))->pluck('name')->sort()->values();

            return $names->isEmpty() ? 'Belum ditugaskan' : $names->implode(', ');
        };
    @endphp

    <x-page-header title="User" description="Akun yang bisa masuk ke sistem, beserta role dan gudangnya.">
        <a href="{{ route('users.create') }}" class="btn btn-primary w-full sm:w-auto"><x-icon name="plus" class="size-4" /> User</a>
    </x-page-header>

    <x-flash />

    {{-- Layar lebar: tabel --}}
    <x-card flush class="hidden lg:block">
        <x-table>
            <thead class="border-b border-gray-100">
                <tr>
                    <th class="table-th">Nama</th>
                    <th class="table-th">Email</th>
                    <th class="table-th">Role</th>
                    <th class="table-th">Gudang</th>
                    <th class="table-th text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($users as $user)
                    <tr>
                        <td class="table-td font-medium text-gray-800">{{ $user->name }} @if ($user->is($me)) <span class="text-xs font-normal text-gray-500">(Anda)</span> @endif</td>
                        <td class="table-td">{{ $user->email }}</td>
                        <td class="table-td"><x-badge variant="{{ $user->isAdministrator() ? 'brand' : 'gray' }}">{{ $user->role?->name ?? 'Tanpa role' }}</x-badge></td>
                        <td class="table-td">{{ $warehouseNames($user) }}</td>
                        <td class="table-td">
                            <div class="flex items-center justify-end gap-2">
                                @if ($canTouch($user))
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-secondary btn-sm">Ubah</a>
                                @endif
                                @if ($canDelete($user))
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Hapus user {{ e($user->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    </x-card>

    {{-- HP dan layar sedang: daftar kartu --}}
    <ul data-mobile-list class="grid gap-3 md:grid-cols-2 lg:hidden">
        @foreach ($users as $user)
            <li class="rounded-2xl border border-gray-200 bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-semibold text-gray-800">{{ $user->name }}
                            @if ($user->is($me)) <span class="ml-1 text-xs font-normal text-gray-500">(Anda)</span> @endif
                        </h2>
                        <p class="truncate text-sm text-gray-500">{{ $user->email }}</p>
                    </div>
                    <x-badge variant="{{ $user->isAdministrator() ? 'brand' : 'gray' }}" class="shrink-0">{{ $user->role?->name ?? 'Tanpa role' }}</x-badge>
                </div>
                <p class="mt-2 text-xs text-gray-500">{{ $warehouseNames($user) }}</p>
                @if ($canTouch($user) || $canDelete($user))
                    <div class="mt-4 flex gap-2">
                        @if ($canTouch($user))
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-secondary btn-sm flex-1">Ubah</a>
                        @endif
                        @if ($canDelete($user))
                            <form method="POST" action="{{ route('users.destroy', $user) }}" class="flex-1" onsubmit="return confirm('Hapus user {{ e($user->name) }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm w-full">Hapus</button>
                            </form>
                        @endif
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
@endsection
