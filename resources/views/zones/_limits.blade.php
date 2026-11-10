{{-- Isian batas suhu dan kelembapan. Kebutuhan: $values (temp_min, temp_max, warning_margin, humidity_min, humidity_max, humidity_warning_margin) --}}
<x-card>
    <h2 class="flex items-center gap-2 text-base font-medium text-gray-800"><x-icon name="thermometer" class="size-5 text-gray-500" /> Suhu (°C)</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
            <label for="temp_min" class="form-label">Minimum</label>
            <input id="temp_min" type="number" name="temp_min" step="0.1" inputmode="decimal" required class="form-input" value="{{ old('temp_min', $values['temp_min']) }}">
        </div>
        <div>
            <label for="temp_max" class="form-label">Maksimum</label>
            <input id="temp_max" type="number" name="temp_max" step="0.1" inputmode="decimal" required class="form-input" value="{{ old('temp_max', $values['temp_max']) }}">
        </div>
    </div>
    <div class="mt-4">
        <label for="warning_margin" class="form-label">Margin Waspada (±°C)</label>
        <input id="warning_margin" type="number" name="warning_margin" step="0.1" min="0" inputmode="decimal" required class="form-input sm:max-w-xs" value="{{ old('warning_margin', $values['warning_margin']) }}">
        <p class="form-hint">Suhu dalam jarak ini dari batas = Waspada (kuning). Di luar batas = Bahaya (merah).</p>
    </div>
</x-card>

<x-card>
    <h2 class="flex items-center gap-2 text-base font-medium text-gray-800"><x-icon name="droplet" class="size-5 text-gray-500" /> Kelembapan (%)</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
            <label for="humidity_min" class="form-label">Minimum</label>
            <input id="humidity_min" type="number" name="humidity_min" step="0.1" min="0" max="100" inputmode="decimal" class="form-input" value="{{ old('humidity_min', $values['humidity_min']) }}">
        </div>
        <div>
            <label for="humidity_max" class="form-label">Maksimum</label>
            <input id="humidity_max" type="number" name="humidity_max" step="0.1" min="0" max="100" inputmode="decimal" class="form-input" value="{{ old('humidity_max', $values['humidity_max']) }}">
        </div>
    </div>
    <p class="form-hint">Kosongkan kedua kolom bila kelembapan zona ini tidak perlu diperiksa.</p>
    <div class="mt-4">
        <label for="humidity_warning_margin" class="form-label">Margin Waspada (±%)</label>
        <input id="humidity_warning_margin" type="number" name="humidity_warning_margin" step="0.1" min="0" inputmode="decimal" required class="form-input sm:max-w-xs" value="{{ old('humidity_warning_margin', $values['humidity_warning_margin']) }}">
    </div>
</x-card>
