<x-form-errors />

<x-card class="max-w-2xl">
    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <div>
            <label for="user-name" class="form-label">Nama</label>
            <input id="user-name" type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="off" class="form-input">
        </div>

        <div>
            <label for="user-email" class="form-label">Email</label>
            <input id="user-email" type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="off" class="form-input">
        </div>

        <div>
            <label for="user-password" class="form-label">Kata sandi{{ $user->exists ? ' (kosongkan bila tidak diganti)' : '' }}</label>
            <input id="user-password" type="password" name="password" @required(! $user->exists) minlength="8" autocomplete="new-password" class="form-input">
        </div>

        <div>
            <label for="user-role" class="form-label">Role</label>
            <select id="user-role" name="role_id" required class="form-input">
                <option value="">— pilih role —</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id) === (string) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>

        <fieldset>
            <legend class="form-label">Gudang yang ditugaskan</legend>
            @php
                $oldWarehouses = old('warehouses');
                $isChecked = fn ($id) => $oldWarehouses !== null ? in_array((string) $id, array_map('strval', (array) $oldWarehouses), true) : in_array($id, $selectedWarehouses, true);
            @endphp
            <div class="space-y-2">
                @forelse ($warehouses as $warehouse)
                    <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">
                        <input type="checkbox" name="warehouses[]" value="{{ $warehouse->id }}" @checked($isChecked($warehouse->id)) class="size-5 rounded border-gray-300">
                        <span>{{ $warehouse->name }}</span>
                    </label>
                @empty
                    <p class="text-sm text-gray-500">Belum ada gudang yang bisa Anda tetapkan.</p>
                @endforelse
            </div>
            <p class="form-hint">Administrator melihat semua gudang tanpa perlu ditugaskan; pilihan ini dipakai bila rolenya kelak bukan Administrator. User lain hanya melihat dan menerima alert gudang yang dicentang.</p>
        </fieldset>

        <div class="flex flex-col gap-2 sm:flex-row">
            <button type="submit" class="btn btn-primary w-full sm:w-auto">Simpan</button>
            <a href="{{ route('users.index') }}" class="btn btn-secondary w-full sm:w-auto">Batal</a>
        </div>
    </form>
</x-card>
