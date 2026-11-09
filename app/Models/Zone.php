<?php

namespace App\Models;

use App\Enums\ProductType;
use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Hidden(['device_token_hash'])]
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'product_type',
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
            'product_type' => ProductType::class,
            'temp_min' => 'float',
            'temp_max' => 'float',
            'humidity_min' => 'float',
            'humidity_max' => 'float',
            'warning_margin' => 'float',
            'humidity_warning_margin' => 'float',
        ];
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
