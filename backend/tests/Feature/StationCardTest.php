<?php

namespace Tests\Feature;

use App\Models\Callsign;
use App\Models\StationCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StationCardTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user): void
    {
        $this->app['auth']->forgetGuards();
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user->fresh(), 'web')->withSession(['two_factor_proof' => hash('sha256', $user->two_factor_secret), 'password_fingerprint' => hash('sha256', $user->password)]);
    }

    private function payload(Callsign $callsign): array
    {
        return ['callsign_id' => $callsign->id, 'title' => 'Mi tarjeta de estación', 'description' => 'Imagen elegida por mí.', 'source' => 'upload', 'is_public' => false, 'image' => UploadedFile::fake()->image('card.jpg', 1200, 750)];
    }

    private function design(): array
    {
        return ['template' => 'aurora', 'background' => '#080f20', 'accent' => '#22d3ee', 'text' => '#ffffff', 'subtitle' => 'Amateur radio', 'location' => 'Ecuador', 'locator' => 'FI09', 'message' => '73', 'positions' => ['callsign' => ['x' => 70, 'y' => 210], 'subtitle' => ['x' => 70, 'y' => 280], 'location' => ['x' => 70, 'y' => 480], 'message' => ['x' => 70, 'y' => 640]]];
    }

    public function test_upload_is_reencoded_private_by_default_and_only_the_owner_can_download(): void
    {
        Storage::fake('local');
        $call = Callsign::factory()->create(['verification_status' => 'verified']);
        $this->login($call->user);

        $response = $this->postJson('/api/v1/station-cards', $this->payload($call))->assertCreated()->assertJsonPath('data.is_public', false)->assertJsonMissingPath('data.file_path');

        $card = StationCard::firstOrFail();
        $this->assertSame("\x89PNG\r\n\x1a\n", substr(Storage::disk('local')->get($card->file_path), 0, 8));
        $this->get($response->json('data.image_url'))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson('/api/v1/public/station-cards')->assertOk()->assertJsonCount(0, 'data');
        $this->get('/api/v1/public/station-cards/'.$card->public_id.'/image')->assertNotFound();
        $this->assertDatabaseHas('station_cards', ['user_id' => $call->user_id, 'callsign_id' => $call->id, 'is_public' => false]);
    }

    public function test_publishing_is_explicit_and_hiding_removes_the_public_image_immediately(): void
    {
        Storage::fake('local');
        $call = Callsign::factory()->create(['verification_status' => 'verified']);
        $this->login($call->user);
        $this->postJson('/api/v1/station-cards', $this->payload($call))->assertCreated();
        $card = StationCard::firstOrFail();
        $url = '/api/v1/station-cards/'.$card->public_id.'/visibility';

        $this->patchJson($url, ['is_public' => true])->assertOk()->assertJsonPath('data.is_public', true);

        $this->getJson('/api/v1/public/station-cards?q='.strtolower($call->callsign))->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.design')->assertJsonMissingPath('data.0.user_id')->assertJsonMissingPath('data.0.file_path');
        $this->get('/api/v1/public/station-cards/'.$card->public_id.'/image')->assertOk();
        $this->patchJson($url, ['is_public' => false])->assertOk();
        $this->get('/api/v1/public/station-cards/'.$card->public_id.'/image')->assertNotFound();
    }

    public function test_editor_data_can_be_reopened_and_replacing_the_image_removes_the_old_file(): void
    {
        Storage::fake('local');
        $call = Callsign::factory()->create(['verification_status' => 'verified']);
        $this->login($call->user);
        $payload = array_merge($this->payload($call), ['source' => 'editor', 'design' => json_encode($this->design())]);
        $this->postJson('/api/v1/station-cards', $payload)->assertCreated()->assertJsonPath('data.design.locator', 'FI09');
        $card = StationCard::firstOrFail();
        $oldPath = $card->file_path;
        $design = $this->design();
        $design['accent'] = '#ff8800';

        $this->putJson('/api/v1/station-cards/'.$card->public_id, array_merge($payload, ['image' => UploadedFile::fake()->image('new.png', 1200, 750), 'design' => $design]))->assertOk()->assertJsonPath('data.design.accent', '#ff8800');

        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($card->fresh()->file_path);
        $this->getJson('/api/v1/station-cards/'.$card->public_id)->assertOk()->assertJsonPath('data.design.accent', '#ff8800');
        $this->assertDatabaseCount('station_cards', 1);
    }

    public function test_other_users_cannot_read_replace_publish_or_delete_private_cards(): void
    {
        Storage::fake('local');
        $card = StationCard::factory()->create();
        $other = Callsign::factory()->create(['verification_status' => 'verified']);
        $this->login($other->user);
        $url = '/api/v1/station-cards/'.$card->public_id;

        $this->getJson($url)->assertNotFound();
        $this->get($url.'/image')->assertNotFound();
        $this->putJson($url, $this->payload($other))->assertNotFound();
        $this->patchJson($url.'/visibility', ['is_public' => true])->assertNotFound();
        $this->deleteJson($url)->assertNotFound();
        $this->getJson('/api/v1/station-cards')->assertOk()->assertJsonCount(0, 'data');
        $this->assertFalse($card->fresh()->is_public);
    }

    public function test_pending_callsigns_can_save_drafts_but_cannot_publish_or_use_other_owners_callsigns(): void
    {
        Storage::fake('local');
        $call = Callsign::factory()->create();
        $this->login($call->user);
        $this->postJson('/api/v1/station-cards', $this->payload($call))->assertCreated();
        $card = StationCard::firstOrFail();

        $this->patchJson('/api/v1/station-cards/'.$card->public_id.'/visibility', ['is_public' => true])->assertUnprocessable()->assertJsonValidationErrors('is_public');
        $other = Callsign::factory()->create(['verification_status' => 'verified']);
        $this->postJson('/api/v1/station-cards', $this->payload($other))->assertUnprocessable()->assertJsonValidationErrors('callsign_id');
        $this->assertDatabaseCount('station_cards', 1);
    }

    #[TestWith(['suspended'])]
    #[TestWith(['unverified'])]
    #[TestWith(['disputed'])]
    #[TestWith(['released'])]
    public function test_hidden_owners_or_callsigns_hide_gallery_entries_and_images(string $state): void
    {
        Storage::fake('local');
        $call = Callsign::factory()->create(['verification_status' => 'verified']);
        $card = StationCard::factory()->create(['callsign_id' => $call->id, 'user_id' => $call->user_id, 'is_public' => true]);
        Storage::disk('local')->put($card->file_path, 'private bytes');
        if ($state === 'suspended') {
            $call->user->forceFill(['status' => 'suspended'])->save();
        } elseif ($state === 'unverified') {
            $call->user->forceFill(['email_verified_at' => null])->save();
        } elseif ($state === 'released') {
            $call->update(['reserved_callsign' => null]);
        } else {
            $call->update(['verification_status' => 'disputed']);
        }

        $this->getJson('/api/v1/public/station-cards')->assertOk()->assertJsonCount(0, 'data');
        $this->get('/api/v1/public/station-cards/'.$card->public_id.'/image')->assertNotFound();
    }

    public function test_invalid_images_and_malformed_designs_are_rejected_without_storing_files(): void
    {
        Storage::fake('local');
        $call = Callsign::factory()->create(['verification_status' => 'verified']);
        $this->login($call->user);
        $payload = $this->payload($call);
        $payload['image'] = UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $this->postJson('/api/v1/station-cards', $payload)->assertUnprocessable()->assertJsonValidationErrors('image');
        $payload = array_merge($this->payload($call), ['source' => 'editor', 'design' => '{invalid-json']);
        $this->postJson('/api/v1/station-cards', $payload)->assertUnprocessable()->assertJsonValidationErrors('design');
        $this->assertDatabaseCount('station_cards', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_deletion_removes_the_stored_image_and_guests_cannot_write(): void
    {
        Storage::fake('local');
        $call = Callsign::factory()->create(['verification_status' => 'verified']);
        $this->postJson('/api/v1/station-cards', $this->payload($call))->assertUnauthorized();
        $this->login($call->user);
        $this->postJson('/api/v1/station-cards', $this->payload($call))->assertCreated();
        $card = StationCard::firstOrFail();

        $this->deleteJson('/api/v1/station-cards/'.$card->public_id)->assertNoContent();

        Storage::disk('local')->assertMissing($card->file_path);
        $this->assertDatabaseCount('station_cards', 0);
    }
}
