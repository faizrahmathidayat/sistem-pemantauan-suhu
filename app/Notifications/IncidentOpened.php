<?php

namespace App\Notifications;

use App\Models\Incident;
use App\Models\SensorLog;
use Illuminate\Notifications\Notification;

/**
 * Dikirim satu kali ketika sebuah insiden dibuka. Isi pesan dipisah dari saluran
 * pengiriman: tambahkan saluran baru (mis. WhatsApp) cukup lewat via() dan
 * sebuah method toXxx(), tanpa menyentuh IncidentManager.
 */
class IncidentOpened extends Notification
{
    public function __construct(
        public readonly Incident $incident,
        public readonly ?SensorLog $log = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $zone = $this->incident->zone;
        $reason = $this->incident->reason;

        return [
            'incident_id' => $this->incident->id,
            'zone_id' => $zone->id,
            'zone_name' => $zone->name,
            'reason' => $reason->value,
            'title' => 'PERINGATAN: '.$reason->label().' di '.$zone->name,
            'temperature' => $this->log?->temperature,
            'humidity' => $this->log?->humidity,
            'limit' => $reason->limitFor($zone),
            'occurred_at' => $this->incident->started_at->toIso8601String(),
            'advice' => $reason->advice(),
            // Relatif: URL lengkap dibentuk saat tampil, mengikuti host yang dipakai pembaca
            // (notifikasi dari scheduler dibuat di CLI, sehingga url() akan memakai APP_URL).
            'url' => '/dashboard',
        ];
    }
}
