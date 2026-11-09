<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcknowledgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_acknowledge_records_user_and_time(): void
    {
        $user = User::factory()->create(['name' => 'Budi']);
        $incident = Incident::factory()->create();

        $this->actingAs($user)->postJson("/incidents/{$incident->id}/acknowledge")
            ->assertOk()->assertJson(['acknowledged_by' => 'Budi']);

        $incident->refresh();
        $this->assertSame($user->id, $incident->acknowledged_by);
        $this->assertNotNull($incident->acknowledged_at);
    }

    public function test_html_form_post_redirects_back(): void
    {
        $incident = Incident::factory()->create();

        $this->actingAs(User::factory()->create())->from('/dashboard')
            ->post("/incidents/{$incident->id}/acknowledge")->assertRedirect('/dashboard');
    }

    public function test_second_acknowledge_keeps_first_user(): void
    {
        $first = User::factory()->create(['name' => 'Budi']);
        $second = User::factory()->create(['name' => 'Sari']);
        $incident = Incident::factory()->create();

        $this->actingAs($first)->postJson("/incidents/{$incident->id}/acknowledge")->assertOk();
        $this->actingAs($second)->postJson("/incidents/{$incident->id}/acknowledge")
            ->assertOk()->assertJson(['acknowledged_by' => 'Budi']);

        $this->assertSame($first->id, $incident->fresh()->acknowledged_by);
    }

    public function test_acknowledge_closed_incident_is_a_noop_without_error(): void
    {
        $incident = Incident::factory()->create(['ended_at' => now()]);

        $this->actingAs(User::factory()->create())->postJson("/incidents/{$incident->id}/acknowledge")->assertOk();

        $this->assertNull($incident->fresh()->acknowledged_by);
    }

    public function test_acknowledge_does_not_close_incident(): void
    {
        $incident = Incident::factory()->create();

        $this->actingAs(User::factory()->create())->postJson("/incidents/{$incident->id}/acknowledge")->assertOk();

        $this->assertTrue($incident->fresh()->isOpen());
    }

    public function test_guest_cannot_acknowledge(): void
    {
        $incident = Incident::factory()->create();

        $this->postJson("/incidents/{$incident->id}/acknowledge")->assertUnauthorized();
        $this->assertNull($incident->fresh()->acknowledged_by);
    }
}
