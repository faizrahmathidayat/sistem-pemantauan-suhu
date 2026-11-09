<?php

namespace App\Models;

use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

#[Hidden(['device_token_hash'])]
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'warehouse_id',
        'device_id',
        'device_token_hash',
        'temp_min',
        'temp_max',
        'humidity_min',
        'humidity_max',
        'warning_margin',
        'humidity_warning_margin',
    ];

    protected function casts(): array
    {
        return [
            'temp_min' => 'float',
            'temp_max' => 'float',
            'humidity_min' => 'float',
            'humidity_max' => 'float',
            'warning_margin' => 'float',
            'humidity_warning_margin' => 'float',
        ];
    }

    /**
     * Zona yang boleh dilihat user (lewat gudangnya): semua untuk Administrator.
     *
     * @param  Builder<Zone>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isAdministrator()) {
            $query->whereIn('warehouse_id', DB::table('warehouse_user')->where('user_id', $user->id)->select('warehouse_id'));
        }
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** Produk yang disimpan di zona ini (nama saja), urut sesuai input. */
    public function products(): HasMany
    {
        return $this->hasMany(ZoneProduct::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Ganti seluruh daftar produk; urutan mengikuti urutan $names.
     *
     * @param  list<string>  $names
     */
    public function syncProducts(array $names): void
    {
        DB::transaction(function () use ($names): void {
            $this->products()->delete();
            foreach (array_values($names) as $position => $name) {
                $this->products()->create(['name' => $name, 'position' => $position]);
            }
        });
        $this->unsetRelation('products');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SensorLog::class, 'device_id', 'device_id');
    }

    public function latestLog(): HasOne
    {
        return $this->hasOne(SensorLog::class, 'device_id', 'device_id')->latestOfMany();
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function openIncidents(): HasMany
    {
        return $this->hasMany(Incident::class)->whereNull('ended_at');
    }
}
