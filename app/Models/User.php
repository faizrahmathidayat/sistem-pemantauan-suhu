<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password', 'role_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'warehouse_user');
    }

    /** Administrator melihat semua gudang; user lain hanya gudang yang di-assign. */
    public function canAccessWarehouse(Warehouse|int|null $warehouse): bool
    {
        if ($warehouse === null) {
            return false;
        }
        if ($this->isAdministrator()) {
            return true;
        }

        return $this->warehouses()->whereKey($warehouse instanceof Warehouse ? $warehouse->getKey() : $warehouse)->exists();
    }

    public function canAccessZone(Zone $zone): bool
    {
        return $this->canAccessWarehouse($zone->warehouse_id);
    }

    /**
     * User yang boleh mengakses gudang tertentu: Administrator atau yang di-assign ke gudang itu.
     *
     * @param  Builder<User>  $query
     */
    public function scopeWithWarehouseAccess(Builder $query, Warehouse|int $warehouse): void
    {
        $id = $warehouse instanceof Warehouse ? $warehouse->getKey() : $warehouse;

        $query->where(fn (Builder $q) => $q
            ->whereHas('role', fn (Builder $role) => $role->where('is_system', true))
            ->orWhereHas('warehouses', fn (Builder $w) => $w->whereKey($id)));
    }

    public function hasPermission(Permission $permission): bool
    {
        return $this->role?->grants($permission) ?? false;
    }

    public function isAdministrator(): bool
    {
        return (bool) $this->role?->is_system;
    }

    /**
     * User yang rolenya sistem (Administrator) atau memiliki izin tersebut.
     *
     * @param  Builder<User>  $query
     */
    public function scopeWithPermission(Builder $query, Permission $permission): void
    {
        $query->whereHas('role', fn (Builder $role) => $role
            ->where('is_system', true)
            ->orWhereIn('id', DB::table('role_permissions')->where('permission', $permission->value)->select('role_id')));
    }
}
