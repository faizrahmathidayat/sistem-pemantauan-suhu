<?php

namespace App\Enums;

/**
 * Daftar izin tetap di kode: tiap izin melindungi fitur nyata. Yang dinamis hanya
 * pemetaan role → izin (tabel role_permissions). Izin baru cukup ditambah sebagai case.
 */
enum Permission: string
{
    case DashboardView = 'dashboard.view';
    case IncidentView = 'incident.view';
    case IncidentAcknowledge = 'incident.acknowledge';
    case NotificationView = 'notification.view';
    case WarehouseManage = 'warehouse.manage';
    case ZoneView = 'zone.view';
    case ZoneUpdate = 'zone.update';
    case ZoneManage = 'zone.manage';
    case AuditLogView = 'auditlog.view';
    case UserManage = 'user.manage';
    case RoleManage = 'role.manage';

    public function label(): string
    {
        return match ($this) {
            self::DashboardView => 'Melihat dashboard',
            self::IncidentView => 'Melihat riwayat insiden',
            self::IncidentAcknowledge => 'Menandai insiden telah ditangani',
            self::NotificationView => 'Melihat notifikasi dan menerima alert',
            self::WarehouseManage => 'Mengelola gudang',
            self::ZoneView => 'Melihat batas zona',
            self::ZoneUpdate => 'Mengubah batas zona',
            self::ZoneManage => 'Mengelola zona dan produk',
            self::AuditLogView => 'Melihat log perubahan',
            self::UserManage => 'Mengelola user',
            self::RoleManage => 'Mengelola role',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::DashboardView => 'Dashboard',
            self::IncidentView, self::IncidentAcknowledge => 'Insiden',
            self::NotificationView => 'Notifikasi',
            self::WarehouseManage => 'Gudang',
            self::ZoneView, self::ZoneUpdate, self::ZoneManage => 'Zona',
            self::AuditLogView => 'Log',
            self::UserManage, self::RoleManage => 'Akses',
        };
    }

    /**
     * @return array<string, list<Permission>> izin per kelompok fitur, dalam urutan tampil
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = $permission;
        }

        return $groups;
    }
}
