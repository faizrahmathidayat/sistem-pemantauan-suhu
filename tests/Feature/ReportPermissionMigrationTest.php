<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportPermissionMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return include database_path('migrations/2026_11_10_100001_grant_report_view_permission.php');
    }

    /** @return list<string> */
    private function holders(): array
    {
        return DB::table('role_permissions')->where('permission', 'report.view')
            ->orderBy('role_id')->pluck('role_id')->map(fn ($id) => (string) $id)->all();
    }

    public function test_grants_report_view_to_roles_with_dashboard_view_only(): void
    {
        $withDashboard = Role::factory()->withPermissions(Permission::DashboardView)->create();
        $zoneOnly = Role::factory()->withPermissions(Permission::ZoneView)->create();
        DB::table('role_permissions')->where('permission', 'report.view')->delete();

        $this->migration()->up();

        $this->assertContains((string) $withDashboard->id, $this->holders());
        $this->assertNotContains((string) $zoneOnly->id, $this->holders());
    }

    public function test_up_is_idempotent(): void
    {
        $role = Role::factory()->withPermissions(Permission::DashboardView)->create();

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(1, DB::table('role_permissions')
            ->where('role_id', $role->id)->where('permission', 'report.view')->count());
    }

    public function test_down_removes_the_permission(): void
    {
        Role::factory()->withPermissions(Permission::DashboardView, Permission::ReportView)->create();
        $this->assertNotEmpty($this->holders());

        $this->migration()->down();

        $this->assertSame([], $this->holders());
    }
}
