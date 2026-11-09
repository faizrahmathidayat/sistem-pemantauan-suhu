<?php

namespace App\Models;

use Database\Factories\SensorLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class SensorLog extends Model
{
    /** @use HasFactory<SensorLogFactory> */
    use HasFactory, MassPrunable;

    public const RETENTION_DAYS = 90;

    protected $fillable = ['device_id', 'temperature', 'humidity'];

    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'humidity' => 'float',
        ];
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subDays(self::RETENTION_DAYS));
    }
}
