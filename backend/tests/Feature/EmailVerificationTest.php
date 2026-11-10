<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordQueued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_verification_marks_only_the_authenticated_users_email(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->actingAs($user, 'web')->getJson($url)->assertNoContent();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_unsigned_verification_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'web')->getJson('/api/v1/auth/email/verify/'.$user->id.'/'.sha1($user->email))->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_password_reset_changes_password_and_consumes_the_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $notification = Notification::sent($user, ResetPasswordQueued::class)->first();
        $payload = ['email' => $user->email, 'token' => $notification->token, 'password' => 'Replacement123!', 'password_confirmation' => 'Replacement123!'];

        $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();

        $this->assertTrue(Hash::check('Replacement123!', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable();
    }

    public function test_password_reset_notification_is_queued(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPasswordQueued::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }
}
