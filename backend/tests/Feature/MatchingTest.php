<?php

namespace Tests\Feature;

use App\Models\Callsign;
use App\Models\Qso;
use App\Models\QsoMatch;
use App\Services\DomainLock;
use App\Services\QsoMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MatchingTest extends TestCase
{
    use RefreshDatabase;

    private function pair(array $override = []): array
    {
        Queue::fake();
        $a = Callsign::factory()->create(['verification_status' => 'verified']);
        $b = Callsign::factory()->create(['verification_status' => 'verified']);
        $left = Qso::factory()->create(['callsign_id' => $a->id, 'user_id' => $a->user_id, 'normalized_contacted_callsign' => $b->callsign]);
        $right = Qso::factory()->create(array_merge(['callsign_id' => $b->id, 'user_id' => $b->user_id, 'normalized_contacted_callsign' => $a->callsign], $override));

        return [$left, $right];
    }

    private function reconcile(): void
    {
        DB::transaction(function () {
            DomainLock::acquire();
            app(QsoMatchingService::class)->reconcile();
        });
    }

    #[DataProvider('cases')]
    public function test_matching_boundaries(array $override, int $count): void
    {
        $this->pair($override);
        $this->reconcile();
        $this->assertSame($count, QsoMatch::where('status', 'candidate')->count());
        $this->assertSame(0, QsoMatch::where('status', 'confirmed')->count());
    }

    public static function cases(): array
    {
        return ['exact' => [[], 1], '10min' => [['started_at' => '2026-01-01 12:10:00'], 1], '30min' => [['started_at' => '2026-01-01 12:30:00'], 1], 'over30' => [['started_at' => '2026-01-01 12:30:01'], 0], 'negative30' => [['started_at' => '2026-01-01 11:30:00'], 1], 'band' => [['band' => '40m'], 0], 'mode' => [['mode' => 'CW'], 0], 'not reciprocal' => [['normalized_contacted_callsign' => 'TEST0OTHER'], 0]];
    }

    public function test_reconciliation_is_idempotent(): void
    {
        $this->pair();
        $this->reconcile();
        $this->reconcile();
        $this->assertDatabaseCount('qso_matches', 1);
        $this->assertDatabaseCount('notification_deliveries', 2);
    }

    public function test_ambiguity_invalidates_previous_candidate_and_partial_signature(): void
    {
        [$a,$b] = $this->pair();
        $this->reconcile();
        app(QsoMatchingService::class)->decide($a->user, QsoMatch::firstOrFail(), 'confirm');
        Qso::factory()->create(['callsign_id' => $b->callsign_id, 'user_id' => $b->user_id, 'normalized_contacted_callsign' => $a->callsign->callsign]);
        $this->reconcile();
        $this->assertSame(0, QsoMatch::where('status', 'candidate')->count());
        $this->assertNull(QsoMatch::firstOrFail()->a_confirmed_at);
        $this->assertSame('pending', $a->fresh()->status);
    }

    public function test_same_user_never_matches(): void
    {
        Queue::fake();
        $a = Callsign::factory()->create(['verification_status' => 'verified']);
        $b = Callsign::factory()->create(['user_id' => $a->user_id, 'verification_status' => 'verified']);
        Qso::factory()->create(['callsign_id' => $a->id, 'user_id' => $a->user_id, 'normalized_contacted_callsign' => $b->callsign]);
        Qso::factory()->create(['callsign_id' => $b->id, 'user_id' => $a->user_id, 'normalized_contacted_callsign' => $a->callsign]);
        $this->reconcile();
        $this->assertDatabaseCount('qso_matches', 0);
    }

    public function test_bilateral_confirmation_is_idempotent_and_excludes_already_confirmed_qsos(): void
    {
        [$a,$b] = $this->pair();
        $this->reconcile();
        $match = QsoMatch::firstOrFail();
        $service = app(QsoMatchingService::class);
        $service->decide($a->user, $match, 'confirm');
        $this->assertSame('candidate', $match->fresh()->status);
        $service->decide($b->user, $match, 'confirm');
        $service->decide($b->user, $match, 'confirm');
        $this->assertDatabaseCount('qso_confirmations', 2);
        Qso::factory()->create(['callsign_id' => $b->callsign_id, 'user_id' => $b->user_id, 'normalized_contacted_callsign' => $a->callsign->callsign]);
        $this->reconcile();
        $this->assertSame(1, QsoMatch::where('status', 'confirmed')->count());
        $this->assertSame(0,QsoMatch::where('status','candidate')->count());
    }
}
