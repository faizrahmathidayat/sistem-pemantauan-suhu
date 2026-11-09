@extends('layouts.app')

@section('title', 'Role')

@section('content')
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold">Role &amp; Hak Akses</h1>
            <p class="text-sm text-slate-500">Role menentukan fitur apa yang boleh dipakai user.</p>
        </div>
        <a href="{{ route('roles.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Role</a>
    </div>

    @if (session('status'))
        <p role="status" class="mt-3 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">✅ {{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p role="alert" class="mt-3 rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ session('error') }}</p>
    @endif

    <ul class="mt-3 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($roles as $role)
            <li class="rounded-xl bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h2 class="font-semibold">{{ $role->name }}
                            @if ($role->is_system)
                                <span class="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700">Sistem</span>
                            @endif
                        </h2>
                        <p class="text-sm text-slate-500">{{ $role->users_count }} user</p>
                    </div>
                    @unless ($role->is_system)
                        <a href="{{ route('roles.edit', $role) }}" class="rounded-lg bg-white px-3 py-2 text-sm font-medium shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">Ubah</a>
                    @endunless
                </div>

                <p class="mt-2 text-sm text-slate-600">
                    @if ($role->is_system)
                        Semua izin (selalu, termasuk izin baru).
                    @else
                        @php($permissions = $role->permissions())
                        {{ count($permissions) }} dari {{ count(\App\Enums\Permission::cases()) }} izin:
                        {{ $permissions === [] ? 'tidak ada' : implode(', ', array_map(fn ($p) => $p->label(), $permissions)) }}
                    @endif
                </p>

                @unless ($role->is_system)
                    <form method="POST" action="{{ route('roles.destroy', $role) }}" class="mt-3"
                          onsubmit="return confirm('Hapus role {{ e($role->name) }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-700 underline">Hapus</button>
                    </form>
                @endunless
            </li>
        @endforeach
    </ul>
@endsection
