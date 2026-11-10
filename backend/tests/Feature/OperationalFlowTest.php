<?php

namespace Tests\Feature;

use App\Models\Callsign;
use App\Models\QslCard;
use App\Models\QsoMatch;
use App\Models\User;
use App\Services\QsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Tests\TestCase;

class OperationalFlowTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $user = $user->fresh();
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            app(EnableTwoFactorAuthentication::class)($user);
            $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        }
        $this->actingAs($user->fresh(), 'web')->withSession(['two_factor_proof' => hash('sha256', $user->two_factor_secret), 'password_fingerprint' => hash('sha256', $user->password)]);
    }

    private function payload(Callsign $mine, Callsign $other): array
    {
        return ['callsign_id' => $mine->id, 'contacted_callsign' => $other->callsign, 'frequency_khz' => '14074.125', 'band' => '20m', 'mode' => 'FT8', 'started_at' => '2026-01-01T12:00:00Z', 'notes' => '<script>private note</script>'];
    }

    public function test_two_users_confirm_issue_and_publicly_verify_a_real_png(): void
    {
        Queue::fake();
        Storage::fake('local');
        $a = Callsign::factory()->create(['verification_status' => 'verified']);
        $b = Callsign::factory()->create(['verification_status' => 'verified']);
        $this->login($a->user);
        $this->postJson('/api/v1/qsos', $this->payload($a, $b))->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->login($b->user);
        $this->postJson('/api/v1/qsos', $this->payload($b, $a))->assertCreated()->assertJsonPath('data.status', 'candidate');
        $match = QsoMatch::firstOrFail();
        $this->postJson('/api/v1/matches/'.$match->id.'/qsl-cards')->assertConflict();
        $this->patchJson('/api/v1/matches/'.$match->id, ['decision' => 'confirm'])->assertOk()->assertJsonPath('data.status', 'candidate');
        $this->login($a->user);
        $this->patchJson('/api/v1/matches/'.$match->id, ['decision' => 'confirm'])->assertOk()->assertJsonPath('data.status', 'confirmed');
        $response = $this->postJson('/api/v1/matches/'.$match->id.'/qsl-cards')->assertSuccessful();
        $id = $response->json('data.public_id');
        $this->postJson('/api/v1/matches/'.$match->id.'/qsl-cards')->assertSuccessful()->assertJsonPath('data.public_id', $id);
        $this->assertDatabaseCount('qsl_cards', 1);
        $card = QslCard::firstOrFail();
        $this->assertSame("\x89PNG\r\n\x1a\n", substr(Storage::disk('local')->get($card->file_path), 0, 8));
        $this->get('/api/v1/qsl-cards/'.$id.'/download')->assertOk()->assertHeader('content-type', 'image/png');
        $this->getJson('/api/v1/public/qsl-cards/'.$id)->assertOk()->assertJsonPath('data.confirmation_status', 'confirmed')->assertJsonMissingPath('data.email')->assertJsonMissingPath('data.notes')->assertJsonMissingPath('data.file_path');
        $this->login($b->user);
        $this->patchJson('/api/v1/matches/'.$match->id, ['decision' => 'dispute'])->assertOk();
        $this->getJson('/api/v1/public/qsl-cards/'.$id)->assertOk()->assertJsonPath('data.status', 'revoked');
        $this->getJson('/api/v1/qsl-cards/'.$id.'/download')->assertConflict();
    }

    public function test_cross_user_privacy_and_validation(): void
    {
        Queue::fake();
        $a = Callsign::factory()->create(['verification_status' => 'verified']);
        $b = Callsign::factory()->create(['verification_status' => 'verified']);
        $qso = app(QsoService::class)->create($a->user, $this->payload($a, $b));
        $this->login($a->user);
        $this->getJson('/api/v1/qsos?to=2026-01-01')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/qsos?from=2026-01-02&to=2026-01-01')->assertUnprocessable();
        $this->login($b->user);
        $this->getJson('/api/v1/qsos/'.$qso->id)->assertNotFound();
        $this->getJson('/api/v1/qsos')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/qsos', array_merge($this->payload($b, $a), ['frequency_khz' => '14.074125']))->assertUnprocessable()->assertJsonValidationErrors('frequency_khz');
        $this->postJson('/api/v1/qsos', array_merge($this->payload($b, $a), ['started_at' => '2026-01-01T12:00:00-05:00']))->assertUnprocessable()->assertJsonValidationErrors('started_at');
    }

    public function test_outsiders_cannot_confirm_issue_or_read_another_pairs_cards(): void
    {
        Queue::fake();
        $a = Callsign::factory()->create(['verification_status' => 'verified']);
        $b = Callsign::factory()->create(['verification_status' => 'verified']);
        app(QsoService::class)->create($a->user, $this->payload($a, $b));
        app(QsoService::class)->create($b->user, $this->payload($b, $a));
        $match = QsoMatch::firstOrFail();
        $card = QslCard::create(['public_id' => (string) Str::uuid(), 'qso_match_id' => $match->id, 'sender_user_id' => $a->user_id, 'receiver_user_id' => $b->user_id, 'status' => 'pending']);
        $this->login(User::factory()->create());

        $this->getJson('/api/v1/matches')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson('/api/v1/matches/'.$match->id, ['decision' => 'confirm'])->assertNotFound();
        $this->postJson('/api/v1/matches/'.$match->id.'/qsl-cards')->assertNotFound();
        $this->getJson('/api/v1/qsl-cards/'.$card->public_id)->assertNotFound();
        $this->getJson('/api/v1/qsl-cards/'.$card->public_id.'/download')->assertNotFound();
        $this->getJson('/api/v1/qsl-cards')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_private_groups_require_authentication_and_second_factor(): void
    {
        foreach (['qsos', 'matches', 'qsl-cards', 'dashboard', 'notifications', 'admin/callsigns'] as $path) {
            $this->getJson('/api/v1/'.$path)->assertUnauthorized();
        }
        $this->actingAs(User::factory()->create(), 'web');
        foreach (['qsos', 'matches', 'qsl-cards', 'dashboard', 'notifications'] as $path) {
            $this->getJson('/api/v1/'.$path)->assertForbidden();
        }
    }

    public function test_suspension_and_password_change_invalidate_sessions(): void
    {
        $user = User::factory()->create();
        $this->login($user);
        $user->forceFill(['status' => 'suspended'])->save();
        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $user->forceFill(['status' => 'active'])->save();
        $this->login($user);
        $user->forceFill(['password' => 'Replacement123!'])->save();
        $this->getJson('/api/v1/profile')->assertUnauthorized();
    }
}
