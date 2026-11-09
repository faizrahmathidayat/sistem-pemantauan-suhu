@extends('layouts.app')

@section('title', 'User')

@section('content')
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold">User</h1>
            <p class="text-sm text-slate-500">Akun yang bisa masuk ke sistem, beserta role dan gudangnya.</p>
        </div>
        <a href="{{ route('users.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ User</a>
    </div>

    @if (session('status'))
        <p role="status" class="mt-3 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">✅ {{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p role="alert" class="mt-3 rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ session('error') }}</p>
    @endif

    <ul class="mt-3 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($users as $user)
            <li class="rounded-xl bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="truncate font-semibold">{{ $user->name }}
                            @if ($user->is(auth()->user()))
                                <span class="ml-1 text-xs font-normal text-slate-500">(Anda)</span>
                            @endif
                        </h2>
                        <p class="truncate text-sm text-slate-500">{{ $user->email }}</p>
                    </div>
                    @if (auth()->user()->isAdministrator() || ! $user->isAdministrator())
                        <a href="{{ route('users.edit', $user) }}" class="rounded-lg bg-white px-3 py-2 text-sm font-medium shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">Ubah</a>
                    @endif
                </div>
                <p class="mt-2 text-sm">
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $user->role?->name ?? 'Tanpa role' }}</span>
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    @if ($user->isAdministrator())
                        Semua gudang
                    @else
                        @php($names = $user->warehouses->filter(fn ($w) => $visibleWarehouseIds === null || in_array($w->id, $visibleWarehouseIds, true))->pluck('name')->sort()->values())
                        {{ $names->isEmpty() ? 'Belum ditugaskan' : $names->implode(', ') }}
                    @endif
                </p>
                @if (! $user->is(auth()->user()) && (auth()->user()->isAdministrator() || ! $user->isAdministrator()))
                    <form method="POST" action="{{ route('users.destroy', $user) }}" class="mt-3"
                          onsubmit="return confirm('Hapus user {{ e($user->name) }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-700 underline">Hapus</button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>
@endsection
