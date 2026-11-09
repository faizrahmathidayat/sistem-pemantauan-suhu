<?php

namespace App\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    protected $fillable = ['name'];

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
