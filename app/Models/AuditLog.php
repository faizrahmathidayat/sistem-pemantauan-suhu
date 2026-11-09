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
        'role.created' => 'Menambah role',
        'role.updated' => 'Mengubah role',
        'role.deleted' => 'Menghapus role',
        'user.created' => 'Menambah user',
        'user.updated' => 'Mengubah user',
        'user.deleted' => 'Menghapus user',
    ];

    /** Kolom yang bernilai null berarti "tidak diperiksa" (batas kelembapan boleh dikosongkan). */
    private const NULL_MEANS_UNCHECKED = ['humidity_min', 'humidity_max'];

    /** Label dan satuan tiap kolom yang dicatat. */
    public const FIELDS = [
        'temp_min' => ['Suhu minimum', '°C'],
        'temp_max' => ['Suhu maksimum', '°C'],
        'humidity_min' => ['Kelembapan minimum', '%'],
        'humidity_max' => ['Kelembapan maksimum', '%'],
        'warning_margin' => ['Margin Waspada suhu', '°C'],
        'humidity_warning_margin' => ['Margin Waspada kelembapan', '%'],
        'name' => ['Nama', ''],
        'email' => ['Email', ''],
        'role' => ['Role', ''],
        'password' => ['Kata sandi', ''],
        'permissions' => ['Izin', ''],
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
                'from' => $this->formatValue($field, $change['from'] ?? null, $unit),
                'to' => $this->formatValue($field, $change['to'] ?? null, $unit),
            ];
        }

        return $lines;
    }

    private function formatValue(string $field, mixed $value, string $unit): string
    {
        if (is_array($value)) {
            return $value === [] ? '–' : implode(', ', array_map('strval', $value));
        }

        if ($value === null) {
            return in_array($field, self::NULL_MEANS_UNCHECKED, true) ? 'tidak diperiksa' : '–';
        }

        if (! is_int($value) && ! is_float($value)) {
            return (string) $value;
        }

        $number = rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');

        return trim("{$number} {$unit}");
    }
}
