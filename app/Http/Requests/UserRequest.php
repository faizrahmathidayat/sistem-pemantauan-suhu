<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin `user.manage` dijaga middleware pada route
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            // Saat mengubah user, kata sandi kosong berarti "tidak diganti".
            'password' => [$user === null ? 'required' : 'nullable', 'string', 'min:8'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'warehouses' => ['nullable', 'array'],
            'warehouses.*' => ['integer', Rule::exists('warehouses', 'id')],
        ];
    }

    /**
     * User non-Administrator hanya boleh menetapkan gudang yang ia sendiri punya.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->user()->isAdministrator()) {
                return;
            }

            $allowed = $this->user()->warehouses()->pluck('warehouses.id')->all();
            foreach ((array) $this->input('warehouses', []) as $index => $id) {
                if (! $validator->errors()->has("warehouses.{$index}") && ! in_array((int) $id, $allowed, true)) {
                    $validator->errors()->add("warehouses.{$index}", 'Anda tidak boleh menetapkan gudang ini.');
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah dipakai user lain.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'role_id.required' => 'Pilih role.',
            'role_id.exists' => 'Role tidak ditemukan.',
            'warehouses.*.exists' => 'Gudang tidak ditemukan.',
            'warehouses.*.integer' => 'Gudang tidak valid.',
        ];
    }
}
