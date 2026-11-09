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
