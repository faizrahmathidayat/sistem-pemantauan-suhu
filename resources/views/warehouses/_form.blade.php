<x-form-errors />

<x-card class="max-w-2xl">
    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        @if ($warehouse->exists)
            @method('PUT')
        @endif

        <div>
            <label for="warehouse-name" class="form-label">Nama gudang</label>
            <input id="warehouse-name" type="text" name="name" value="{{ old('name', $warehouse->name) }}" required maxlength="100" autocomplete="off"
                   class="form-input" placeholder="mis. Gudang Cikokol (Telur)">
        </div>

        <div class="flex flex-col gap-2 sm:flex-row">
            <button type="submit" class="btn btn-primary w-full sm:w-auto">Simpan</button>
            <a href="{{ route('warehouses.index') }}" class="btn btn-secondary w-full sm:w-auto">Batal</a>
        </div>
    </form>
</x-card>
