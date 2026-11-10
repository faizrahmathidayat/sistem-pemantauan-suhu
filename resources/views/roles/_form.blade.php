@php
    $old = old('permissions');
    $isChecked = fn (\App\Enums\Permission $p) => $old !== null ? in_array($p->value, (array) $old, true) : in_array($p, $selected, true);
@endphp

<x-form-errors />

<form method="POST" action="{{ $action }}" class="max-w-4xl space-y-4">
    @csrf
    @if ($role->exists)
        @method('PUT')
    @endif

    <x-card>
        <label for="role-name" class="form-label">Nama role</label>
        <input id="role-name" type="text" name="name" value="{{ old('name', $role->name) }}" required maxlength="50" class="form-input sm:max-w-md">
    </x-card>

    <div class="grid gap-4 md:grid-cols-2">
        @foreach (\App\Enums\Permission::grouped() as $group => $permissions)
            <fieldset class="rounded-2xl border border-gray-200 bg-white p-5">
                <legend class="px-1 text-base font-medium text-gray-800">{{ $group }}</legend>
                <div class="mt-2 space-y-2">
                    @foreach ($permissions as $permission)
                        <label class="flex items-center gap-3 rounded-lg px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->value }}" @checked($isChecked($permission)) class="size-5 rounded border-gray-300">
                            <span>{{ $permission->label() }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </div>

    <div class="flex flex-col gap-2 sm:flex-row">
        <button type="submit" class="btn btn-primary w-full sm:w-auto">Simpan</button>
        <a href="{{ route('roles.index') }}" class="btn btn-secondary w-full sm:w-auto">Batal</a>
    </div>
</form>
