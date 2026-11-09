<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Izin `role.manage` dijaga middleware pada route; role sistem tidak boleh diubah sama sekali.
        return ! $this->route('role')?->is_system;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var Role|null $role */
        $role = $this->route('role');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('roles', 'name')->ignore($role?->id)],
            'permissions' => ['nullable', 'array'],
            // Nilai di luar daftar izin (form dipalsukan) ditolak, bukan diabaikan diam-diam.
            'permissions.*' => ['string', Rule::enum(Permission::class)],
        ];
    }

    /**
     * @return list<Permission> izin terpilih, tanpa duplikat, dalam urutan daftar izin
     */
    public function selectedPermissions(): array
    {
        $chosen = array_map(fn (string $v) => Permission::from($v), $this->validated('permissions') ?? []);

        return array_values(array_filter(Permission::cases(), fn (Permission $p) => in_array($p, $chosen, true)));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama role wajib diisi.',
            'name.unique' => 'Nama role sudah dipakai.',
            'name.max' => 'Nama role maksimal 50 karakter.',
            'permissions.*.enum' => 'Ada izin yang tidak dikenal.',
        ];
    }
}
