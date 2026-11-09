<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'temp_min' => 10, 'temp_max' => 20,
            'humidity_min' => 60, 'humidity_max' => 85,
            'warning_margin' => 2, 'humidity_warning_margin' => 5,
        ], $overrides);
    }

    private function zone(array $overrides = []): Zone
    {
        return Zone::factory()->create(array_merge([
            'name' => 'Zona Telur', 'temp_min' => 10, 'temp_max' => 20,
            'humidity_min' => 60, 'humidity_max' => 85, 'warning_margin' => 2, 'humidity_warning_margin' => 5,
        ], $overrides));
    }

    public function test_updating_limits_records_who_changed_what_from_and_to(): void
    {
        $budi = User::factory()->create(['name' => 'Budi Santoso']);
        $zone = $this->zone();

        $this->actingAs($budi)->put("/zones/{$zone->id}", $this->payload([
            'temp_max' => 22, 'humidity_min' => '', 'humidity_max' => '',
        ]))->assertSessionHasNoErrors();

        $log = AuditLog::sole();
        $this->assertSame($budi->id, $log->user_id);
        $this->assertSame('Budi Santoso', $log->user_name);
        $this->assertSame('zone.limits_updated', $log->event);
        $this->assertSame(Zone::class, $log->subject_type);
        $this->assertSame($zone->id, $log->subject_id);
        $this->assertSame('Zona Telur', $log->subject_label);
        $this->assertEquals([
            'temp_max' => ['from' => 20.0, 'to' => 22.0],
            'humidity_min' => ['from' => 60.0, 'to' => null],
            'humidity_max' => ['from' => 85.0, 'to' => null],
        ], $log->diff);
        // assertEquals menyamakan null dengan 0, jadi pastikan "tidak diperiksa" benar-benar null.
        $this->assertNull($log->diff['humidity_min']['to']);
        $this->assertNull($log->diff['humidity_max']['to']);
    }

    public function test_saving_without_any_change_records_nothing(): void
    {
        $zone = $this->zone();

        $this->actingAs(User::factory()->create())->put("/zones/{$zone->id}", $this->payload())
            ->assertRedirect(route('zones.index'));

        $this->assertSame(0, AuditLog::count());
    }

    public function test_rejected_input_records_nothing(): void
    {
        $zone = $this->zone();

        $this->actingAs(User::factory()->create())
            ->put("/zones/{$zone->id}", $this->payload(['temp_min' => 30, 'temp_max' => 20]))
            ->assertSessionHasErrors('temp_max');

        $this->assertSame(0, AuditLog::count());
    }

    public function test_change_is_rolled_back_when_the_audit_record_cannot_be_written(): void
    {
        $zone = $this->zone();
        $this->app->bind(AuditLogger::class, fn () => new class extends AuditLogger
        {
            public function record(?User $user, string $event, Model $subject, string $label, array $changes): AuditLog
            {
                throw new RuntimeException('log gagal');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs(User::factory()->create())->put("/zones/{$zone->id}", $this->payload(['temp_max' => 22]));
            $this->fail('Pengecualian dari pencatat audit seharusnya diteruskan.');
        } catch (RuntimeException) {
            // diharapkan
        }

        $this->assertSame(20.0, $zone->refresh()->temp_max);
    }

    public function test_log_page_lists_newest_first_with_user_zone_and_before_after(): void
    {
        $viewer = User::factory()->create();
        AuditLog::factory()->create([
            'user_name' => 'Andi Lama', 'subject_label' => 'Zona Lama', 'created_at' => now()->subDays(2),
            'diff' => ['temp_min' => ['from' => 8.0, 'to' => 9.0]],
        ]);
        AuditLog::factory()->create([
            'user_name' => 'Budi Baru', 'subject_label' => 'Zona Baru', 'created_at' => now()->subMinutes(5),
            'diff' => [
                'temp_max' => ['from' => 20.0, 'to' => 22.5],
                'humidity_min' => ['from' => 60.0, 'to' => null],
            ],
        ]);

        $this->actingAs($viewer)->get('/audit-logs')
            ->assertOk()
            ->assertSeeInOrder(['Budi Baru', 'Andi Lama'])
            ->assertSee('Zona Baru')
            ->assertSee('Mengubah batas zona')
            ->assertSee('Suhu maksimum')
            ->assertSee('20 °C')
            ->assertSee('22,5 °C')
            ->assertSee('Kelembapan minimum')
            ->assertSee('60 %')
            ->assertSee('tidak diperiksa');
    }

    public function test_log_survives_deleted_user_and_deleted_zone(): void
    {
        $budi = User::factory()->create(['name' => 'Budi Santoso']);
        $zone = $this->zone();
        $this->actingAs($budi)->put("/zones/{$zone->id}", $this->payload(['temp_max' => 22]));

        $zone->delete();
        $budi->delete();

        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/audit-logs')
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Zona Telur');
        $this->assertNull(AuditLog::sole()->user_id);
    }

    public function test_empty_log_renders(): void
    {
        $this->actingAs(User::factory()->create())->get('/audit-logs')
            ->assertOk()
            ->assertSee('Belum ada perubahan');
    }

    public function test_log_paginates_at_20(): void
    {
        AuditLog::factory()->count(21)->create();

        $viewer = User::factory()->create();
        $first = $this->actingAs($viewer)->get('/audit-logs')->viewData('logs');
        $this->assertSame(20, $first->count());
        $this->assertSame(21, $first->total());
        $this->assertSame(1, $this->actingAs($viewer)->get('/audit-logs?page=2')->viewData('logs')->count());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/audit-logs')->assertRedirect(route('login'));
    }

    public function test_navigation_links_to_the_log(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertSee('/audit-logs', false);
    }

    public function test_role_events_are_recorded_with_permission_lists(): void
    {
        $actor = User::factory()->create(['name' => 'Budi Santoso']);

        $this->actingAs($actor)->post('/roles', ['name' => 'Kepala Gudang', 'permissions' => ['zone.view', 'dashboard.view']]);
        $created = AuditLog::sole();
        $this->assertSame('role.created', $created->event);
        $this->assertSame('Budi Santoso', $created->user_name);
        $this->assertSame('Kepala Gudang', $created->subject_label);
        $this->assertSame('Kepala Gudang', $created->diff['name']['to']);
        $this->assertNull($created->diff['name']['from']);
        $this->assertSame([], $created->diff['permissions']['from']);
        $this->assertSame(['Melihat dashboard', 'Melihat batas zona'], $created->diff['permissions']['to']);

        $role = Role::where('name', 'Kepala Gudang')->sole();

        // Menyimpan tanpa perubahan tidak mencatat apa pun.
        $this->actingAs($actor)->put("/roles/{$role->id}", ['name' => 'Kepala Gudang', 'permissions' => ['dashboard.view', 'zone.view']]);
        $this->assertSame(1, AuditLog::count());

        $this->actingAs($actor)->put("/roles/{$role->id}", ['name' => 'Kepala Gudang', 'permissions' => ['dashboard.view', 'zone.update']]);
        $updated = AuditLog::where('event', 'role.updated')->sole();
        $this->assertArrayNotHasKey('name', $updated->diff);
        $this->assertSame(['Melihat dashboard', 'Melihat batas zona'], $updated->diff['permissions']['from']);
        $this->assertSame(['Melihat dashboard', 'Mengubah batas zona'], $updated->diff['permissions']['to']);

        $this->actingAs($actor)->delete("/roles/{$role->id}");
        $deleted = AuditLog::where('event', 'role.deleted')->sole();
        $this->assertSame('Kepala Gudang', $deleted->subject_label);
        $this->assertSame('Kepala Gudang', $deleted->diff['name']['from']);
    }

    public function test_log_page_formats_string_and_list_values(): void
    {
        AuditLog::factory()->create([
            'event' => 'role.updated', 'subject_type' => Role::class, 'subject_label' => 'Kepala Gudang',
            'diff' => [
                'name' => ['from' => null, 'to' => 'Nama Baru'],
                'permissions' => ['from' => ['Melihat dashboard', 'Melihat batas zona'], 'to' => []],
            ],
        ]);

        $this->actingAs(User::factory()->create())->get('/audit-logs')
            ->assertOk()
            ->assertSee('Mengubah role')
            ->assertSee('Nama Baru')
            ->assertSee('Melihat dashboard, Melihat batas zona')
            ->assertSee('–')
            ->assertDontSee('tidak diperiksa');
    }
}
