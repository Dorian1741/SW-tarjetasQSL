<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CallsignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CallsignServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_normalizes_without_verifying_ownership(): void
    {
        $user = User::factory()->create();

        $callsign = app(CallsignService::class)->claim($user, ' test0abc/p ');

        $this->assertSame('TEST0ABC/P', $callsign->normalized_callsign);
        $this->assertSame('pending', $callsign->verification_status);
        $this->assertNull($callsign->verified_at);
        $this->assertNull($callsign->country_code);
        $this->assertTrue($callsign->is_primary);
        $this->assertModelExists($callsign);
    }

    public function test_another_user_cannot_claim_the_same_normalized_callsign(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $service = app(CallsignService::class);
        $service->claim($owner, 'TEST0ABC');

        try {
            $service->claim($other, 'test0abc');
            $this->fail('Una reclamación duplicada debe rechazarse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('callsign', $exception->errors());
        }

        $this->assertDatabaseCount('callsigns', 1);
        $this->assertSame(0, $other->callsigns()->count());
    }

    #[DataProvider('invalidCallsigns')]
    public function test_rejects_obviously_invalid_callsigns(string $value): void
    {
        $this->expectException(ValidationException::class);

        app(CallsignService::class)->validate($value);
    }

    public static function invalidCallsigns(): array
    {
        return [[''], ['ABC'], ['1234'], ['A B1'], ['TEST0ABC//P'], ['<script>'], ['TEST0ABC/P/X/Y']];
    }

    public function test_only_the_first_claim_is_primary(): void
    {
        $user = User::factory()->create();
        $service = app(CallsignService::class);
        $service->claim($user, 'TEST0ABC');

        $second = $service->claim($user, 'TEST0DEF');

        $this->assertFalse($second->is_primary);
        $this->assertSame(1, $user->callsigns()->where('is_primary', true)->count());
    }

    public function test_two_factor_secrets_are_encrypted_and_hidden(): void
    {
        $user = User::factory()->create();
        app(EnableTwoFactorAuthentication::class)($user);

        $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);
        $this->assertNotSame($secret, $user->getRawOriginal('two_factor_secret'));
        $this->assertCount(8, $user->recoveryCodes());
        $this->assertArrayNotHasKey('two_factor_secret', $user->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $user->toArray());
    }
}
