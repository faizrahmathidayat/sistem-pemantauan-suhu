<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesZoneLimits;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Hanya batas suhu/kelembapan yang boleh diubah dari halaman Batas Zona; nama, produk,
 * device_id, dan token perangkat ada di form pengelolaan zona.
 */
class UpdateZoneRequest extends FormRequest
{
    use ValidatesZoneLimits;

    public function authorize(): bool
    {
        // Izin `zone.update` dijaga middleware pada route; di sini hanya zona di gudang yang boleh diakses.
        return $this->user()->canAccessZone($this->route('zone'));
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeLimitNumbers();
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return $this->limitRules();
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkLimits($validator)];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->limitAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->limitMessages();
    }
}
