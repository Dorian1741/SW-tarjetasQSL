<?php

namespace Tests\Feature;

use App\Models\Callsign;
use App\Models\OperatorPost;
use App\Models\OperatorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PublicCommunityTest extends TestCase
{
    use RefreshDatabase;

    private function publicProfile(string $callsign = 'TEST0PUBLIC'): OperatorProfile
    {
        $claim = Callsign::factory()->create(['callsign' => $callsign, 'normalized_callsign' => $callsign, 'reserved_callsign' => $callsign, 'verification_status' => 'verified']);

        return OperatorProfile::create(['user_id' => $claim->user_id, 'callsign_id' => $claim->id, 'biography' => 'Estación pública de prueba.', 'country_code' => 'EC', 'is_public' => true]);
    }

    public function test_guests_can_read_the_paginated_wall_without_private_account_data(): void
    {
        $profile = $this->publicProfile();
        for ($i = 1; $i <= 13; $i++) {
            OperatorPost::create(['user_id' => $profile->user_id, 'content' => 'Publicación '.$i]);
        }

        $response = $this->getJson('/api/v1/public/community')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(12, 'posts.data')->assertJsonPath('posts.data.0.content', 'Publicación 13')
            ->assertJsonPath('posts.data.0.author.callsign', 'TEST0PUBLIC')->assertJsonPath('stats.operators', 1)
            ->assertJsonPath('stats.publications', 13)->assertJsonMissingPath('posts.data.0.user_id');

        $this->assertSame(['callsign', 'biography', 'country_code', 'photo_url', 'public_url'], array_keys($response->json('posts.data.0.author')));
        $this->assertStringNotContainsString($profile->user->email, $response->getContent());
        $this->getJson('/api/v1/public/community?page=2')->assertOk()->assertJsonCount(1, 'posts.data')->assertJsonPath('posts.data.0.content', 'Publicación 1');
    }

    #[TestWith(['private'])]
    #[TestWith(['suspended'])]
    #[TestWith(['unverified_email'])]
    #[TestWith(['pending'])]
    #[TestWith(['rejected'])]
    #[TestWith(['disputed'])]
    #[TestWith(['released'])]
    public function test_non_public_profiles_and_their_posts_are_hidden_immediately(string $state): void
    {
        $profile = $this->publicProfile();
        OperatorPost::create(['user_id' => $profile->user_id, 'content' => 'Contenido que debe permanecer privado.']);
        if ($state === 'private') {
            $profile->update(['is_public' => false]);
        } elseif ($state === 'suspended') {
            $profile->user->forceFill(['status' => 'suspended'])->save();
        } elseif ($state === 'unverified_email') {
            $profile->user->forceFill(['email_verified_at' => null])->save();
        } elseif ($state === 'released') {
            $profile->callsign->update(['reserved_callsign' => null]);
        } else {
            $profile->callsign->update(['verification_status' => $state]);
        }

        $this->getJson('/api/v1/public/community')->assertOk()->assertJsonCount(0, 'posts.data')->assertJsonCount(0, 'operators')->assertJsonPath('stats.operators', 0);
        $this->getJson('/api/v1/public/operators')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_guests_can_search_public_callsigns_case_insensitively_including_portable_suffixes(): void
    {
        $this->publicProfile('TEST0FIND/P');
        $this->publicProfile('TEST0OTHER');

        $this->getJson('/api/v1/public/operators?q=find%2Fp')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.callsign', 'TEST0FIND/P')->assertJsonPath('data.0.public_url', '/operators/TEST0FIND%2FP');
        $this->getJson('/api/v1/public/operators?q=MISSING')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/public/operators?q=%25')->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_public_browsing_does_not_allow_guest_writes_or_private_contacts(): void
    {
        $this->publicProfile();

        $this->postJson('/api/v1/operator-posts', ['content' => 'Sin sesión'])->assertUnauthorized();
        $this->putJson('/api/v1/operators/follow/TEST0PUBLIC')->assertUnauthorized();
        $this->getJson('/api/v1/qsos')->assertUnauthorized();
        $this->assertDatabaseCount('operator_posts', 0);
        $this->assertDatabaseCount('operator_follows', 0);
    }
}
