<?php

namespace Tests\Feature;

use App\Models\Callsign;
use App\Models\User;
use App\Services\CallsignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallsignLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_selection_is_owned_and_atomic(): void
    {
        $owner = User::factory()->create();
        $service = app(CallsignService::class);
        $first = $service->claim($owner, 'TEST0AAA');
        $second = $service->claim($owner, 'TEST0BBB');
        $service->primary($owner, $second);
        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame(1, $owner->callsigns()->where('is_primary', true)->count());
    }

    public function test_rejected_claim_can_be_released_and_reclaimed_without_losing_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $service = app(CallsignService::class);
        $old = $service->claim($owner, 'TEST0AAA');
        $service->review($admin, $old, 'rejected', 'Evidencia de prueba insuficiente.');
        $service->release($admin, $old);
        $new = $service->claim($other, 'TEST0AAA');
        $this->assertNull($old->fresh()->reserved_callsign);
        $this->assertSame($other->id, $new->user_id);
        $this->assertDatabaseCount('callsign_reviews', 2);
        $this->assertDatabaseCount('callsigns', 2);
    }

    public function test_admin_cannot_review_own_ownership(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $claim = Callsign::factory()->create(['user_id' => $admin->id]);
        $this->assertFalse($admin->can('review',$claim));
    }
}
