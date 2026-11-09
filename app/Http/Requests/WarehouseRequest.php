<?php

namespace App\Http\Requests;

use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Izin `warehouse.manage` dijaga middleware pada route; di sini hanya gudang yang boleh diakses.
        /** @var Warehouse|null $warehouse */
        $warehouse = $this->route('warehouse');

        return $warehouse === null || $this->user()->canAccessWarehouse($warehouse);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var Warehouse|null $warehouse */
        $warehouse = $this->route('warehouse');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('warehouses', 'name')->ignore($warehouse?->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama gudang wajib diisi.',
            'name.unique' => 'Nama gudang sudah dipakai.',
            'name.max' => 'Nama gudang maksimal 100 karakter.',
        ];
    }
}
