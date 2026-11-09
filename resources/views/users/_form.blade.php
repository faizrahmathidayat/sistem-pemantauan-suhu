@php
    $input = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-base';
@endphp

@if ($errors->any())
    <div role="alert" class="mt-3 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">
        <p class="font-semibold">Perubahan belum disimpan. Periksa isian berikut:</p>
        <ul class="mt-1 list-disc pl-5">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" class="mt-3 space-y-3 rounded-xl bg-white p-4 shadow-sm">
    @csrf
    @if ($user->exists)
        @method('PUT')
    @endif

    <label class="block text-sm">
        <span class="mb-1 block font-medium">Nama</span>
        <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="off" class="{{ $input }}">
    </label>

    <label class="block text-sm">
        <span class="mb-1 block font-medium">Email</span>
        <input type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="off" class="{{ $input }}">
    </label>

    <label class="block text-sm">
        <span class="mb-1 block font-medium">Kata sandi{{ $user->exists ? ' (kosongkan bila tidak diganti)' : '' }}</span>
        <input type="password" name="password" @required(! $user->exists) minlength="8" autocomplete="new-password" class="{{ $input }}">
    </label>

    <label class="block text-sm">
        <span class="mb-1 block font-medium">Role</span>
        <select name="role_id" required class="{{ $input }}">
            <option value="">— pilih role —</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id) === (string) $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
    </label>

    <div class="flex flex-col gap-2 pt-1 sm:flex-row">
        <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-3 font-semibold text-white hover:bg-emerald-700">Simpan</button>
        <a href="{{ route('users.index') }}" class="rounded-lg bg-white px-6 py-3 text-center font-medium shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">Batal</a>
    </div>
</form>
