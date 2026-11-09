<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesZoneLimits;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Support\ZoneProducts;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Membuat zona (route punya {warehouse}) atau mengubah nama dan produknya (route punya {zone}).
 * Saat membuat, batas suhu/kelembapan dan device_id ikut diisi; device_id tidak bisa diubah
 * kemudian karena riwayat sensor terikat padanya, dan batas diubah lewat halaman Batas Zona.
 */
class ZoneManagementRequest extends FormRequest
{
    use ValidatesZoneLimits;

    private function zone(): ?Zone
    {
        return $this->route('zone');
    }

    private function warehouse(): Warehouse
    {
        return $this->route('warehouse') ?? $this->zone()->warehouse;
    }

    public function authorize(): bool
    {
        // Izin `zone.manage` dijaga middleware pada route; di sini hanya gudang yang boleh diakses.
        return $this->user()->canAccessWarehouse($this->warehouse());
    }

    protected function prepareForValidation(): void
    {
        if ($this->zone() === null) {
            $this->normalizeLimitNumbers();
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('zones', 'name')->where('warehouse_id', $this->warehouse()->id)->ignore($this->zone()?->id),
            ],
            'products' => ['nullable', 'string', 'max:5000'],
        ];

        if ($this->zone() === null) {
            $rules['device_id'] = ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/', Rule::unique('zones', 'device_id')];
            $rules += $this->limitRules();
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->zone() === null) {
                $this->checkLimits($validator);
            }

            $lines = preg_split('/\R/u', (string) $this->input('products', '')) ?: [];
            foreach ($lines as $line) {
                if (mb_strlen(trim($line)) > ZoneProducts::MAX_NAME_LENGTH) {
                    $validator->errors()->add('products', 'Nama produk maksimal '.ZoneProducts::MAX_NAME_LENGTH.' karakter.');
                    break;
                }
            }
            if (count($this->selectedProducts()) > ZoneProducts::MAX_PRODUCTS) {
                $validator->errors()->add('products', 'Maksimal '.ZoneProducts::MAX_PRODUCTS.' produk per zona.');
            }
        }];
    }

    /**
     * @return list<string>
     */
    public function selectedProducts(): array
    {
        return ZoneProducts::parse((string) $this->input('products', ''));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->limitAttributes() + ['name' => 'nama zona', 'device_id' => 'device ID', 'products' => 'produk'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->limitMessages() + [
            'name.unique' => 'Nama zona sudah dipakai di gudang ini.',
            'device_id.unique' => 'Device ID sudah dipakai zona lain.',
            'device_id.regex' => 'Device ID hanya boleh huruf kecil, angka, dan tanda minus (-).',
        ];
    }
}
