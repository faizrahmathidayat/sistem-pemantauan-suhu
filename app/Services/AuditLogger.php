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
     * Tidak ada catatan bila tidak ada nilai yang berubah.
     */
    public function saveAndLog(Model $model, User $user, string $event, string $label): ?AuditLog
    {
        return DB::transaction(function () use ($model, $user, $event, $label): ?AuditLog {
            $changes = $this->changesOf($model);
            $model->save();

            return $changes === [] ? null : $this->record($user, $event, $model, $label, $changes);
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
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function changesOf(Model $model): array
    {
        $changes = [];
        foreach (array_keys($model->getDirty()) as $field) {
            $changes[$field] = ['from' => $model->getOriginal($field), 'to' => $model->getAttribute($field)];
        }

        return $changes;
    }
}
