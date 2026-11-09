<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Hanya batas suhu/kelembapan yang boleh diubah dari dashboard; nama, jenis produk,
 * device_id, dan token perangkat sengaja di luar jangkauan form ini.
 */
class UpdateZoneRequest extends FormRequest
{
    private const NUMBER_FIELDS = [
        'temp_min', 'temp_max', 'humidity_min', 'humidity_max', 'warning_margin', 'humidity_warning_margin',
    ];

    public function authorize(): bool
    {
        // Izin `zone.update` dijaga middleware pada route; di sini hanya zona di gudang yang boleh diakses.
        return $this->user()->canAccessZone($this->route('zone'));
    }

    /** Pengguna Indonesia terbiasa menulis "2,5"; PHP/Laravel hanya paham "2.5". */
    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (self::NUMBER_FIELDS as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $normalized[$field] = str_replace(',', '.', trim($value));
            }
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'temp_min' => ['required', 'numeric', 'between:-40,80'],
            'temp_max' => ['required', 'numeric', 'between:-40,80'],
            'humidity_min' => ['nullable', 'required_with:humidity_max', 'numeric', 'between:0,100'],
            'humidity_max' => ['nullable', 'required_with:humidity_min', 'numeric', 'between:0,100'],
            'warning_margin' => ['required', 'numeric', 'min:0', 'max:100'],
            'humidity_warning_margin' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * Pemeriksaan lintas-kolom: hanya dijalankan untuk kolom yang sudah lolos aturan dasarnya.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $errors = $validator->errors();
            $data = $validator->getData();

            if (! $errors->hasAny(['temp_min', 'temp_max'])) {
                $this->checkRange($validator, 'temp_max', 'warning_margin', (float) $data['temp_min'], (float) $data['temp_max']);
            }

            $hasHumidityRange = ($data['humidity_min'] ?? null) !== null && ($data['humidity_max'] ?? null) !== null;
            if ($hasHumidityRange && ! $errors->hasAny(['humidity_min', 'humidity_max'])) {
                $this->checkRange($validator, 'humidity_max', 'humidity_warning_margin', (float) $data['humidity_min'], (float) $data['humidity_max']);
            }
        }];
    }

    private function checkRange(Validator $validator, string $maxField, string $marginField, float $min, float $max): void
    {
        if ($max <= $min) {
            $validator->errors()->add($maxField, 'Batas maksimum harus lebih besar dari batas minimum.');

            return;
        }

        $margin = $validator->getData()[$marginField] ?? null;
        if (is_numeric($margin) && ! $validator->errors()->has($marginField) && (float) $margin * 2 >= $max - $min) {
            $validator->errors()->add($marginField, 'Margin Waspada terlalu besar: dua kali margin harus lebih kecil dari lebar rentang ('.rtrim(rtrim(number_format($max - $min, 2, ',', ''), '0'), ',').'), kalau tidak, tidak ada kondisi Aman yang tersisa.');
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'temp_min' => 'suhu minimum',
            'temp_max' => 'suhu maksimum',
            'humidity_min' => 'kelembapan minimum',
            'humidity_max' => 'kelembapan maksimum',
            'warning_margin' => 'margin Waspada suhu',
            'humidity_warning_margin' => 'margin Waspada kelembapan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'required_with' => 'Isi kedua batas kelembapan, atau kosongkan keduanya.',
            'numeric' => ':Attribute harus berupa angka.',
            'between' => ':Attribute harus di antara :min dan :max.',
            'min' => ':Attribute tidak boleh kurang dari :min.',
            'max' => ':Attribute tidak boleh lebih dari :max.',
        ];
    }
}
