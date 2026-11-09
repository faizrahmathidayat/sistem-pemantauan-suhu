<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan perubahan model sekaligus mencatat siapa yang mengubahnya.
 * Keduanya satu transaksi: perubahan tanpa catatan tidak boleh terjadi.
 */
class AuditLogger
{
    /**
     * Simpan $model (yang sudah di-fill) dan catat selisih nilai lama dan barunya.
     * Kolom di $ignore dan kolom tersembunyi model (mis. kata sandi) tidak ikut dicatat;
     * $extra menambah entri yang dihitung pemanggil (mis. nama role, daftar izin).
     * Tidak ada catatan bila tidak ada nilai yang berubah.
     *
     * @param  array<string, array{from: mixed, to: mixed}>  $extra
     * @param  list<string>  $ignore
     */
    public function saveAndLog(Model $model, ?User $actor, string $event, string $label, array $extra = [], array $ignore = []): ?AuditLog
    {
        return DB::transaction(function () use ($model, $actor, $event, $label, $extra, $ignore): ?AuditLog {
            $changes = $this->changesOf($model, array_merge($ignore, $model->getHidden())) + $extra;
            $model->save();

            return $changes === [] ? null : $this->record($actor, $event, $model, $label, $changes);
        });
    }

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    public function record(?User $user, string $event, Model $subject, string $label, array $changes): AuditLog
    {
        return AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Sistem',
            'event' => $event,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'subject_label' => $label,
            'diff' => $changes,
        ]);
    }

    /**
     * @param  list<string>  $ignore
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function changesOf(Model $model, array $ignore): array
    {
        $changes = [];
        foreach (array_keys($model->getDirty()) as $field) {
            if (in_array($field, $ignore, true)) {
                continue;
            }
            $changes[$field] = ['from' => $model->getOriginal($field), 'to' => $model->getAttribute($field)];
        }

        return $changes;
    }
}
