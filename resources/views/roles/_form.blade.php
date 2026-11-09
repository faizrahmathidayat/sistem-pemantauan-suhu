@php
    $input = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-base';
    $old = old('permissions');
    $isChecked = fn (\App\Enums\Permission $p) => $old !== null ? in_array($p->value, (array) $old, true) : in_array($p, $selected, true);
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

<form method="POST" action="{{ $action }}" class="mt-3 space-y-4">
    @csrf
    @if ($role->exists)
        @method('PUT')
    @endif

    <label class="block rounded-xl bg-white p-4 text-sm shadow-sm">
        <span class="mb-1 block font-medium">Nama role</span>
        <input type="text" name="name" value="{{ old('name', $role->name) }}" required maxlength="50" class="{{ $input }}">
    </label>

    @foreach (\App\Enums\Permission::grouped() as $group => $permissions)
        <fieldset class="rounded-xl bg-white p-4 shadow-sm">
            <legend class="px-1 font-semibold">{{ $group }}</legend>
            <div class="space-y-2">
                @foreach ($permissions as $permission)
                    <label class="flex items-center gap-3 text-sm">
                        <input type="checkbox" name="permissions[]" value="{{ $permission->value }}" @checked($isChecked($permission)) class="h-5 w-5">
                        <span>{{ $permission->label() }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach

    <div class="flex flex-col gap-2 sm:flex-row">
        <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-3 font-semibold text-white hover:bg-emerald-700">Simpan</button>
        <a href="{{ route('roles.index') }}" class="rounded-lg bg-white px-6 py-3 text-center font-medium shadow-sm hover:bg-slate-50">Batal</a>
    </div>
</form>
