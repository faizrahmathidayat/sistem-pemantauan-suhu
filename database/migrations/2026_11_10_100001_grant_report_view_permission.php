<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin baru `report.view`. Role yang sudah bisa melihat dashboard sudah bisa melihat datanya,
 * jadi mereka diberi izin ini agar tidak kehilangan akses; Administrator dapat mencabutnya
 * di menu Role. Hanya memakai DB (tanpa model) agar tetap bisa dijalankan walau model berubah.
 */
return new class extends Migration
{
    private const PERMISSION = 'report.view';

    public function up(): void
    {
        $roleIds = DB::table('role_permissions')
            ->where('permission', 'dashboard.view')
            ->whereNotIn('role_id', DB::table('role_permissions')->where('permission', self::PERMISSION)->select('role_id'))
            ->pluck('role_id');

        foreach ($roleIds as $roleId) {
            DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission' => self::PERMISSION]);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission', self::PERMISSION)->delete();
    }
};
