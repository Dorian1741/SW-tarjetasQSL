<?php

namespace Tests\Feature;

use App\Models\Qso;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_rejects_a_qso_using_another_users_callsign(): void
    {
        $qso = Qso::factory()->create();
        $other = User::factory()->create();
        $this->expectException(QueryException::class);

        Qso::factory()->create(['callsign_id' => $qso->callsign_id, 'user_id' => $other->id]);
    }

    public function test_demo_seeder_is_repeatable_without_duplicate_users_callsigns_or_qsos(): void
    {
        $this->seed();

        $this->seed();

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('callsigns', 2);
        $this->assertDatabaseCount('qsos', 2);
        $this->assertDatabaseHas('callsigns', ['callsign' => 'TEST0DEMOA', 'verification_status' => 'pending']);
    }

    public function test_frequency_round_trips_without_float_conversion(): void
    {
        $qso = Qso::factory()->create(['frequency_khz' => '14074.125']);

        $this->assertSame('14074.125', $qso->fresh()->frequency_khz);
        $this->assertSame('2026-01-01T12:00:00+00:00', $qso->fresh()->started_at->utc()->format('c'));
        $this->assertSame($qso->user_id, $qso->callsign->user_id);
    }
}
