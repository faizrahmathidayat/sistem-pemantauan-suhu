<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan siapa mengubah apa. Hanya ditulis, tidak pernah diubah (tanpa updated_at).
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** Label kejadian; tambahkan entri di sini untuk jenis perubahan baru. */
    public const EVENTS = [
        'zone.limits_updated' => 'Mengubah batas zona',
    ];

    /** Label dan satuan tiap kolom yang dicatat. */
    public const FIELDS = [
        'temp_min' => ['Suhu minimum', '°C'],
        'temp_max' => ['Suhu maksimum', '°C'],
        'humidity_min' => ['Kelembapan minimum', '%'],
        'humidity_max' => ['Kelembapan maksimum', '%'],
        'warning_margin' => ['Margin Waspada suhu', '°C'],
        'humidity_warning_margin' => ['Margin Waspada kelembapan', '%'],
    ];

    protected $fillable = [
        'user_id',
        'user_name',
        'event',
        'subject_type',
        'subject_id',
        'subject_label',
        'diff',
    ];

    protected function casts(): array
    {
        return [
            'diff' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventLabel(): string
    {
        return self::EVENTS[$this->event] ?? $this->event;
    }

    /**
     * Baris siap tampil: label kolom, nilai lama, nilai baru.
     *
     * @return list<array{label: string, from: string, to: string}>
     */
    public function changeLines(): array
    {
        $lines = [];
        foreach ($this->diff as $field => $change) {
            [$label, $unit] = self::FIELDS[$field] ?? [$field, ''];
            $lines[] = [
                'label' => $label,
                'from' => $this->formatValue($change['from'] ?? null, $unit),
                'to' => $this->formatValue($change['to'] ?? null, $unit),
            ];
        }

        return $lines;
    }

    private function formatValue(mixed $value, string $unit): string
    {
        if ($value === null) {
            return 'tidak diperiksa';
        }

        $number = rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');

        return trim("{$number} {$unit}");
    }
}
