<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSensorLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autentikasi ditangani middleware `device`
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:64'],
            'temperature' => ['required', 'numeric', 'between:-40,80'],
            'humidity' => ['required', 'numeric', 'between:0,100'],
        ];
    }
}
