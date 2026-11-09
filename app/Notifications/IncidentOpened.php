<?php

namespace App\Notifications;

use App\Enums\IncidentReason;
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
            'limit' => match ($reason) {
                IncidentReason::SuhuTinggi => $zone->temp_max,
                IncidentReason::SuhuRendah => $zone->temp_min,
                IncidentReason::LembapTinggi => $zone->humidity_max,
                IncidentReason::LembapRendah => $zone->humidity_min,
                IncidentReason::SensorOffline => null,
            },
            'occurred_at' => $this->incident->started_at->toIso8601String(),
            'advice' => $reason->advice(),
            'url' => url('/dashboard'),
        ];
    }
}
