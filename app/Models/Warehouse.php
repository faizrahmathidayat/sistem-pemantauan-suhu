<?php

namespace App\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Hidden(['monitor_token_hash'])]
class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * Buat kode monitor baru (kode lama langsung tidak berlaku). Hanya hash sha256 yang
     * disimpan; nilai asli dikembalikan sekali ini saja.
     */
    public function issueMonitorToken(): string
    {
        $token = Str::random(40);
        $this->forceFill(['monitor_token_hash' => hash('sha256', $token)])->save();

        return $token;
    }

    public function revokeMonitorToken(): void
    {
        $this->forceFill(['monitor_token_hash' => null])->save();
    }

    public function hasMonitorToken(): bool
    {
        return $this->monitor_token_hash !== null;
    }

    public static function findByMonitorToken(?string $token): ?self
    {
        if ($token === null || $token === '') {
            return null;
        }

        return static::query()->where('monitor_token_hash', hash('sha256', $token))->first();
    }

    /**
     * Gudang yang boleh dilihat user: semua untuk Administrator, selain itu yang di-assign.
     *
     * @param  Builder<Warehouse>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isAdministrator()) {
            $query->whereIn('id', DB::table('warehouse_user')->where('user_id', $user->id)->select('warehouse_id'));
        }
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'warehouse_user');
    }
}
