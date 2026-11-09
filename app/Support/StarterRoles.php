<?php

namespace App\Support;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Role awal sebuah instalasi. Idempoten: aman dipanggil berulang (migrasi dan seeder).
 */
final class StarterRoles
{
    /** @var array<string, list<Permission>> */
    private const STARTERS = [
        'Supervisor' => [
            Permission::DashboardView, Permission::IncidentView, Permission::IncidentAcknowledge,
            Permission::NotificationView, Permission::ZoneView, Permission::ZoneUpdate, Permission::AuditLogView,
        ],
        'Operator' => [
            Permission::DashboardView, Permission::IncidentView, Permission::IncidentAcknowledge,
            Permission::NotificationView,
        ],
    ];

    public static function install(): void
    {
        $administrator = Role::administrator();

        foreach (self::STARTERS as $name => $permissions) {
            // Role yang sudah ada tidak ditimpa: izinnya mungkin sudah disesuaikan pengguna.
            $role = Role::firstOrCreate(['name' => $name], ['is_system' => false]);
            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($permissions);
            }
        }

        User::whereNull('role_id')->update(['role_id' => $administrator->id]);
    }
}
