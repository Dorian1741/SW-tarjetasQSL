<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailQueued;
use App\Services\CallsignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_queues_verification_and_does_not_expose_secrets(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', ['name' => 'Test', 'email' => 'TEST@EXAMPLE.TEST', 'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!'])->assertCreated();

        $user = User::where('email', 'test@example.test')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmailQueued::class);
        $this->assertNull($user->email_verified_at);
        $this->getJson('/api/v1/profile')->assertOk()->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.two_factor_secret');
        $this->getJson('/api/v1/callsigns')->assertForbidden();
    }

    public function test_csrf_is_required_outside_the_test_environment(): void
    {
        $this->app->instance('env', 'local');

        $this->postJson('/api/v1/auth/login', ['email' => 'test@example.test', 'password' => 'password'])->assertStatus(419);
    }

    public function test_profile_cannot_elevate_administrative_permissions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->patchJson('/api/v1/profile', ['name' => 'Updated', 'is_admin' => true, 'email_verified_at' => now()->toISOString()])->assertOk();

        $this->assertFalse($user->fresh()->is_admin);
        $this->assertSame('Updated', $user->fresh()->name);
    }

    public function test_guests_cannot_read_private_data(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $this->getJson('/api/v1/callsigns')->assertUnauthorized();
    }

    public function test_password_only_login_cannot_use_operational_routes(): void
    {
        User::factory()->create(['email' => 'test@example.test']);

        $this->postJson('/api/v1/auth/login', ['email' => 'test@example.test', 'password' => 'password'])->assertOk();

        $this->getJson('/api/v1/callsigns')->assertForbidden();
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/profile')->assertUnauthorized();
    }

    public function test_totp_must_be_confirmed_before_claiming_and_only_owned_callsigns_are_listed(): void
    {
        $user = User::factory()->create();
        app(EnableTwoFactorAuthentication::class)($user);
        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
        $this->getJson('/api/v1/callsigns')->assertForbidden();

        $this->postJson('/api/v1/auth/user/confirmed-two-factor-authentication', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->actingAs($user->fresh(), 'web');
        $this->postJson('/api/v1/callsigns', ['callsign' => 'TEST0ABC'])->assertCreated()->assertJsonPath('data.verification_status', 'pending');
        $other = User::factory()->create();
        app(CallsignService::class)->claim($other, 'TEST0OTHER');
        $this->getJson('/api/v1/callsigns')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.callsign', 'TEST0ABC');
    }

    public function test_login_requires_totp_and_recovery_code_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $code = $user->recoveryCodes()[0];

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->assertJsonPath('two_factor', true);
        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $this->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => $code])->assertNoContent();
        $this->getJson('/api/v1/callsigns')->assertOk();
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => $code])->assertUnprocessable();
        $this->getJson('/api/v1/profile')->assertUnauthorized();
    }

    public function test_disabled_users_cannot_login(): void
    {
        $user = User::factory()->create(['status' => 'suspended']);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();

        $this->getJson('/api/v1/profile')->assertUnauthorized();
    }
}
