<?php

namespace App\Models;

use App\Enums\IncidentReason;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    protected $fillable = [
        'zone_id',
        'reason',
        'started_at',
        'ended_at',
        'peak_value',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => IncidentReason::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'peak_value' => 'float',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    /** Lama insiden, mis. "29 mnt" atau "2 jam 5 mnt" (berjalan sampai sekarang bila masih terbuka). */
    public function durationLabel(): string
    {
        $end = $this->ended_at ?? now();
        $minutes = max(0, intdiv($end->getTimestamp() - $this->started_at->getTimestamp(), 60));

        if ($minutes < 60) {
            return "{$minutes} mnt";
        }

        $rest = $minutes % 60;

        return intdiv($minutes, 60).' jam'.($rest > 0 ? " {$rest} mnt" : '');
    }
}
