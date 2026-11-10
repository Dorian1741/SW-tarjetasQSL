<?php

namespace Tests\Feature;

use App\Models\Callsign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Tests\TestCase;

class CallsignReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_review_records_reviewer_date_and_reason(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        app(EnableTwoFactorAuthentication::class)($admin);
        $admin->forceFill(['two_factor_confirmed_at' => now()])->save();
        $callsign = Callsign::factory()->create();

        $this->actingAs($admin->fresh(), 'web')->withSession(['two_factor_proof' => hash('sha256', $admin->two_factor_secret)])
            ->postJson('/api/v1/admin/callsigns/'.$callsign->id.'/review', ['status' => 'verified', 'reason' => 'Licencia de prueba revisada manualmente.'])->assertOk();

        $this->assertSame($admin->id, $callsign->fresh()->reviewed_by);
        $this->assertNotNull($callsign->fresh()->verified_at);
        $this->assertSame('Licencia de prueba revisada manualmente.', $callsign->fresh()->review_reason);
        $this->assertDatabaseHas('security_events', ['user_id' => $admin->id, 'event' => 'callsign.'.$callsign->id.'.verified']);
    }

    public function test_non_admin_cannot_review_a_callsign(): void
    {
        $user = User::factory()->create();
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $callsign = Callsign::factory()->create();

        $this->actingAs($user->fresh(), 'web')->withSession(['two_factor_proof' => hash('sha256', $user->two_factor_secret)])
            ->postJson('/api/v1/admin/callsigns/'.$callsign->id.'/review', ['status' => 'verified', 'reason' => 'Licencia de prueba revisada manualmente.'])->assertNotFound();

        $this->assertSame('pending', $callsign->fresh()->verification_status);
        $this->assertDatabaseCount('security_events', 0);
    }
}
