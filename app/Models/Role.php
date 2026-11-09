<?php

namespace App\Models;

use App\Enums\Permission;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    public const ADMINISTRATOR = 'Administrator';

    protected $fillable = ['name', 'is_system'];

    /** @var list<Permission>|null */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Role sistem yang selalu memiliki semua izin (termasuk izin yang ditambahkan kelak). */
    public static function administrator(): self
    {
        return self::firstOrCreate(['name' => self::ADMINISTRATOR], ['is_system' => true]);
    }

    public function grants(Permission $permission): bool
    {
        return $this->is_system || in_array($permission, $this->permissions(), true);
    }

    /**
     * Izin yang tersimpan untuk role ini (role sistem memakai semua izin lewat grants()).
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return $this->permissionCache ??= DB::table('role_permissions')
            ->where('role_id', $this->getKey())
            ->pluck('permission')
            ->map(fn (string $value) => Permission::tryFrom($value))
            ->filter()
            // Urutan tetap sesuai daftar izin, bukan urutan penyimpanan.
            ->sortBy(fn (Permission $p) => array_search($p, Permission::cases(), true))
            ->values()
            ->all();
    }

    /**
     * Ganti seluruh izin role. Tidak berlaku untuk role sistem (selalu semua izin).
     *
     * @param  list<Permission>  $permissions
     */
    public function syncPermissions(array $permissions): void
    {
        if ($this->is_system) {
            return;
        }

        $values = array_values(array_unique(array_map(fn (Permission $p) => $p->value, $permissions)));

        DB::transaction(function () use ($values): void {
            DB::table('role_permissions')->where('role_id', $this->getKey())->delete();
            DB::table('role_permissions')->insert(array_map(
                fn (string $value) => ['role_id' => $this->getKey(), 'permission' => $value],
                $values,
            ));
        });

        $this->permissionCache = null;
    }
}
