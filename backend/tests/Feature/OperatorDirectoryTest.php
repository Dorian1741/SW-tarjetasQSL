<?php

namespace Tests\Feature;

use App\Models\Callsign;
use App\Models\OperatorProfile;
use App\Models\OwnershipRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OperatorDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user): void
    {
        $this->app['auth']->forgetGuards();
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user->fresh(), 'web')->withSession(['two_factor_proof' => hash('sha256', $user->two_factor_secret), 'password_fingerprint' => hash('sha256', $user->password)]);
    }

    private function publicProfile(): OperatorProfile
    {
        $claim = Callsign::factory()->create(['verification_status' => 'verified']);

        return OperatorProfile::create(['user_id' => $claim->user_id, 'callsign_id' => $claim->id, 'biography' => 'Texto elegido por el usuario.', 'is_public' => true]);
    }

    public function test_registration_requires_callsign_and_duplicate_claim_rolls_back_user(): void
    {
        Notification::fake();
        $payload = ['email' => 'one@example.test', 'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!'];
        $this->postJson('/api/v1/auth/register', $payload)->assertUnprocessable()->assertJsonValidationErrors('callsign');
        $this->postJson('/api/v1/auth/register', $payload + ['callsign' => ' hc1vtb '])->assertCreated();
        $this->assertDatabaseHas('callsigns', ['reserved_callsign' => 'HC1VTB', 'verification_status' => 'pending']);
        $this->assertDatabaseHas('operator_profiles', ['is_public' => false]);
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/register', array_merge($payload, ['email' => 'two@example.test', 'callsign' => 'HC1VTB']))->assertUnprocessable()->assertJsonValidationErrors('callsign');
        $this->assertDatabaseMissing('users', ['email' => 'two@example.test']);
        $this->getJson('/api/v1/public/operators/HC1VTB')->assertNotFound();
    }

    public function test_lookup_requires_authentication_and_never_verifies_ownership_or_publishes_external_data(): void
    {
        $this->postJson('/api/v1/callsigns/lookup', ['callsign' => 'HC1VTB'])->assertUnauthorized();
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        config(['callbook.hamqth.enabled' => true, 'callbook.hamqth.username' => 'test', 'callbook.hamqth.password' => 'secret']);
        Http::preventStrayRequests();
        Http::fake(['https://www.hamqth.com/api/v2/login' => Http::response(['token' => 'secret-token', 'expiresIn' => 3600]), 'https://www.hamqth.com/api/v2/callbook/*' => Http::response(['callsign' => 'HC1VTB', 'name' => 'PRIVATE', 'email' => 'hidden@example.test', 'country' => 'Ecuador'])]);
        $this->postJson('/api/v1/callsigns/lookup', ['callsign' => ' hc1vtb ', 'provider' => 'hamqth'])->assertOk()->assertJsonPath('data.format_valid', true)->assertJsonPath('data.directory_status', 'found')->assertJsonPath('data.ownership_verified', false)->assertDontSee('hidden@example.test')->assertDontSee('secret-token');
        $this->assertDatabaseCount('operator_profiles', 0);
        $this->assertDatabaseCount('callsigns', 0);
        $this->postJson('/api/v1/callsigns/lookup', ['callsign' => 'bad input'])->assertUnprocessable();
    }

    public function test_automatic_lookup_falls_back_and_not_found_ignores_disabled_providers(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        config(['callbook.qrz.enabled' => true, 'callbook.qrz.username' => 'qrz-user', 'callbook.qrz.password' => 'qrz-secret', 'callbook.hamqth.enabled' => true, 'callbook.hamqth.username' => 'ham-user', 'callbook.hamqth.password' => 'ham-secret']);
        Http::preventStrayRequests();
        Http::fake(['https://xmldata.qrz.com/xml/current/' => Http::response('', 503), 'https://www.hamqth.com/api/v2/login' => Http::response(['token' => 'token', 'expiresIn' => 3600]), 'https://www.hamqth.com/api/v2/callbook/*' => Http::sequence()->push(['callsign' => 'HC1VTB', 'country' => 'Ecuador'])->pushStatus(404)]);
        $this->postJson('/api/v1/callsigns/lookup', ['callsign' => 'HC1VTB'])->assertOk()->assertJsonPath('data.result.provider', 'hamqth')->assertJsonPath('data.providers.0.status', 'unavailable');
        config(['callbook.qrz.enabled' => false]);
        $this->postJson('/api/v1/callsigns/lookup', ['callsign' => 'HC1VTB'])->assertOk()->assertJsonPath('data.directory_status', 'not_found');
    }

    public function test_unavailable_directory_is_not_reported_as_nonexistent(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        config(['callbook.qrz.enabled' => false, 'callbook.hamqth.enabled' => false]);
        $this->postJson('/api/v1/callsigns/lookup', ['callsign' => 'HC1VTB'])->assertStatus(503)->assertJsonPath('data.directory_status', 'unavailable');
    }

    public function test_public_profile_contains_only_opted_in_local_data_and_history_is_private_by_default(): void
    {
        $profile = $this->publicProfile();
        $call = $profile->callsign->callsign;
        $this->getJson('/api/v1/public/operators/'.$call)->assertOk()->assertJsonPath('data.biography', 'Texto elegido por el usuario.')->assertJsonMissingPath('data.email')->assertJsonMissingPath('data.user_id')->assertJsonMissingPath('data.photo_path');
        $this->getJson('/api/v1/public/operator-history/'.$call)->assertNotFound();
        $profile->update(['is_public' => false]);
        $this->getJson('/api/v1/public/operators/'.$call)->assertNotFound();
    }

    public function test_pending_ownership_cannot_publish_and_foreign_callsign_cannot_be_used(): void
    {
        $claim = Callsign::factory()->create();
        $this->login($claim->user);
        $payload = ['callsign_id' => $claim->id, 'biography' => 'Test', 'country_code' => null, 'links' => [], 'is_public' => true, 'show_qsl_history' => false];
        $this->putJson('/api/v1/operator-profile', $payload)->assertUnprocessable();
        $other = Callsign::factory()->create(['verification_status' => 'verified']);
        $this->putJson('/api/v1/operator-profile', array_merge($payload, ['callsign_id' => $other->id]))->assertUnprocessable();
        $this->assertDatabaseCount('operator_profiles', 0);
    }

    public function test_ownership_request_is_private_unique_pending_and_reviewed_with_audit(): void
    {
        $claim = Callsign::factory()->create();
        $this->login($claim->user);
        $url = '/api/v1/callsigns/'.$claim->id.'/ownership-requests';
        $this->postJson($url, ['evidence' => 'Licencia presentada por canal privado al administrador.'])->assertCreated();
        $this->postJson($url, ['evidence' => 'Otra evidencia presentada por canal privado.'])->assertConflict();
        $other = User::factory()->create();
        $this->login($other);
        $this->getJson($url)->assertNotFound();
        $this->postJson($url, ['evidence' => 'Prueba ajena'])->assertNotFound();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->login($admin);
        $this->getJson($url)->assertOk();
        $this->postJson('/api/v1/admin/callsigns/'.$claim->id.'/review', ['status' => 'verified', 'reason' => 'Licencia revisada manualmente por un administrador.'])->assertOk();
        $this->assertSame('verified', OwnershipRequest::firstOrFail()->status);
        $this->assertDatabaseHas('callsign_reviews', ['callsign_id' => $claim->id, 'reviewer_id' => $admin->id]);
    }

    public function test_follows_are_idempotent_and_posts_are_native_owned_and_escaped_as_text(): void
    {
        $profile = $this->publicProfile();
        $viewer = User::factory()->create();
        $this->login($viewer);
        $url = '/api/v1/operators/follow/'.$profile->callsign->callsign;
        $this->putJson($url)->assertNoContent();
        $this->putJson($url)->assertNoContent();
        $this->assertDatabaseCount('operator_follows', 1);
        $this->login($profile->user);
        $response = $this->postJson('/api/v1/operator-posts', ['content' => '<script>Texto propio</script>'])->assertCreated();
        $id = $response->json('data.id');
        $this->getJson('/api/v1/public/operator-posts/'.$profile->callsign->callsign)->assertOk()->assertJsonPath('data.0.content', '<script>Texto propio</script>');
        $this->login($viewer);
        $this->deleteJson('/api/v1/operator-posts/'.$id)->assertNotFound();
        $this->assertDatabaseCount('operator_posts', 1);
    }

    public function test_photo_is_reencoded_and_hidden_when_profile_is_private_and_links_reject_unsafe_schemes(): void
    {
        Storage::fake('local');
        $profile = $this->publicProfile();
        $this->login($profile->user);
        $image = new Process([config('qsl.imagemagick'), '-size', '2x2', 'xc:cyan', 'png:-']);
        $image->mustRun();
        $file = UploadedFile::fake()->createWithContent('photo.png', $image->getOutput());
        $this->postJson('/api/v1/operator-profile/photo', ['photo' => $file])->assertOk();
        $profile->refresh();
        $this->get('/api/v1/public/operator-photos/'.$profile->id)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", Storage::disk('local')->get($profile->photo_path));
        $this->putJson('/api/v1/operator-profile', ['callsign_id' => $profile->callsign_id, 'links' => [['label' => 'Unsafe', 'url' => 'javascript:alert(1)']], 'is_public' => true, 'show_qsl_history' => false])->assertUnprocessable();
        $profile->update(['is_public' => false]);
        $this->getJson('/api/v1/public/operator-photos/'.$profile->id)->assertNotFound();
    }

    public function test_lookup_requires_verified_email_and_has_per_user_rate_limit(): void
    {
        $this->actingAs(User::factory()->unverified()->create(), 'web');
        $this->postJson('/api/v1/callsigns/lookup', ['callsign' => 'HC1VTB'])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::factory()->create(), 'web');
        config(['callbook.qrz.enabled' => false, 'callbook.hamqth.enabled' => false]);
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/callsigns/lookup', ['callsign' => 'HC1VTB'])->assertStatus(503);
        }
        $this->postJson('/api/v1/callsigns/lookup', ['callsign' => 'HC1VTB'])->assertTooManyRequests();
    }

    public function test_suspension_or_ownership_dispute_hides_public_profile(): void
    {
        $profile = $this->publicProfile();
        $call = $profile->callsign->callsign;
        $profile->callsign->update(['verification_status' => 'disputed']);
        $this->getJson('/api/v1/public/operators/'.$call)->assertNotFound();
    }
}
