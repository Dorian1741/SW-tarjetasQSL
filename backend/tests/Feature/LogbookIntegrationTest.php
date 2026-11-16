<?php

namespace Tests\Feature;

use App\Models\Callsign;
use App\Models\IntegrationCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Tests\TestCase;

class LogbookIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function credential(array $abilities = ['qsos:read', 'qsos:write']): array
    {
        Queue::fake();
        $claim = Callsign::factory()->create(['verification_status' => 'verified']);
        $user = $claim->user;
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->app['auth']->forgetGuards();
        $this->actingAs($user->fresh(), 'web')->withSession(['two_factor_proof' => hash('sha256', $user->two_factor_secret), 'password_fingerprint' => hash('sha256', $user->password), 'auth.password_confirmed_at' => time()]);
        $response = $this->postJson('/api/v1/integration-credentials', ['callsign_id' => $claim->id, 'name' => 'Libro de guardia de pruebas', 'abilities' => $abilities, 'expires_in_days' => 30])->assertCreated();

        return [$response->json('token'), IntegrationCredential::findOrFail($response->json('data.id')), $claim];
    }

    private function payload(): array
    {
        return ['external_id' => 'log-2026-001', 'contacted_callsign' => 'TEST0OTHER', 'frequency_khz' => '14074.125', 'band' => '20m', 'mode' => 'FT8', 'started_at' => '2026-01-01T12:00:00Z'];
    }

    private function send(string $token, array $payload): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/integrations/qsos', $payload);
    }

    public function test_import_is_idempotent_and_does_not_confirm_automatically(): void
    {
        [$token,$credential,$claim] = $this->credential();
        $this->send($token, $this->payload())->assertCreated()->assertJsonPath('data.qso.status', 'pending')->assertJsonPath('replayed', false);
        $this->send($token, array_merge($this->payload(), ['contacted_callsign' => ' test0other ', 'frequency_khz' => '014074.125']))->assertOk()->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('qsos', 1);
        $this->assertDatabaseCount('integration_receipts', 1);
        $this->assertDatabaseCount('qso_confirmations', 0);
        $this->getJson('/api/v1/integrations/qsos/log-2026-001')->assertOk()->assertJsonPath('data.external_id', 'log-2026-001');
        $this->send($token, array_merge($this->payload(), ['notes' => 'Cambio posterior']))->assertConflict();
        $this->assertDatabaseCount('qsos', 1);
    }

    public function test_tokens_are_hashed_hidden_expiring_and_revocable(): void
    {
        [$token,$credential] = $this->credential();
        $this->assertSame(hash('sha256', $token), $credential->getRawOriginal('token_hash'));
        $this->assertArrayNotHasKey('token_hash', $credential->toArray());
        $this->getJson('/api/v1/integration-credentials')->assertOk()->assertJsonMissingPath('data.0.token')->assertDontSee($token);
        $this->deleteJson('/api/v1/integration-credentials/'.$credential->id)->assertNoContent();
        $this->send($token, $this->payload())->assertUnauthorized();
    }

    public function test_rotation_keeps_external_references_and_invalidates_old_token(): void
    {
        [$token,$credential] = $this->credential();
        $this->send($token, $this->payload())->assertCreated();
        $response = $this->postJson('/api/v1/integration-credentials/'.$credential->id.'/rotate', ['expires_in_days' => 30])->assertOk();
        $new = $response->json('token');
        $this->assertNotSame($token, $new);
        $this->send($token, $this->payload())->assertUnauthorized();
        $this->send($new, $this->payload())->assertOk()->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('qsos', 1);
    }

    public function test_ability_and_station_boundaries_are_enforced(): void
    {
        [$read] = $this->credential(['qsos:read']);
        $this->send($read, $this->payload())->assertForbidden();
        [$write,$credential,$claim] = $this->credential(['qsos:write']);
        $this->send($write, array_merge($this->payload(), ['station_callsign' => 'TEST0FOREIGN']))->assertUnprocessable();
        $this->send($write, array_merge($this->payload(), ['callsign_id' => 99999, 'user_id' => 99999, 'status' => 'confirmed']))->assertCreated()->assertJsonPath('data.qso.callsign_id', $claim->id)->assertJsonPath('data.qso.status', 'pending');
        $this->getJson('/api/v1/integrations/qsos/log-2026-001')->assertForbidden();
    }

    public function test_cookie_session_is_not_an_integration_credential(): void
    {
        [$token] = $this->credential();
        $this->postJson('/api/v1/integrations/qsos', $this->payload())->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer nexus_'.str_repeat('-', 36).'.'.str_repeat('a', 64))->getJson('/api/v1/integrations/qsos')->assertUnauthorized();
    }

    public function test_credentials_cannot_read_other_integrations_or_manage_other_owners(): void
    {
        [$a,$first] = $this->credential();
        $this->send($a, $this->payload())->assertCreated();
        [$b] = $this->credential();
        $this->withHeader('Authorization', 'Bearer '.$b)->getJson('/api/v1/integrations/qsos/log-2026-001')->assertNotFound();
        $this->deleteJson('/api/v1/integration-credentials/'.$first->id)->assertNotFound();
    }

    public function test_expiry_suspension_password_change_and_ownership_loss_disable_access(): void
    {
        [$token,$credential,$claim] = $this->credential();
        $credential->update(['expires_at' => now()->subSecond()]);
        $this->send($token, $this->payload())->assertUnauthorized();
        $credential->update(['expires_at' => now()->addDay()]);
        $claim->update(['verification_status' => 'disputed']);
        $this->send($token, $this->payload())->assertUnauthorized();
        $claim->update(['verification_status' => 'verified']);
        $credential->user->forceFill(['password' => 'Replacement123!'])->save();
        $this->send($token, $this->payload())->assertUnauthorized();
    }

    public function test_token_does_not_authenticate_the_spa_and_json_is_required(): void
    {
        [$token] = $this->credential();
        $this->withHeader('Authorization', 'Bearer '.$token)->post('/api/v1/integrations/qsos', $this->payload())->assertStatus(415);
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/profile')->assertUnauthorized();
    }

    public function test_per_credential_quota_is_enforced(): void
    {
        [$token] = $this->credential(['qsos:read']);
        $this->withHeader('Authorization', 'Bearer '.$token);
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/integrations/qsos')->assertOk();
        }
        $this->getJson('/api/v1/integrations/qsos')->assertTooManyRequests()->assertHeader('Retry-After');
    }

    public function test_issue_requires_verified_ownership_and_recent_password(): void
    {
        [$token,$credential,$claim] = $this->credential();
        $this->withSession(['auth.password_confirmed_at' => 1])->postJson('/api/v1/integration-credentials',['callsign_id' => $claim->id, 'name' => 'Test', 'abilities' => ['qsos:read'], 'expires_in_days' => 30])->assertStatus(423);
    }
}
