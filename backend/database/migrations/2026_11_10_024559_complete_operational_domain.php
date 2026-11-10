<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_locks', function (Blueprint $t) {
            $t->string('name')->primary();
        });
        DB::table('domain_locks')->insert(['name' => 'qsos']);
        Schema::create('qso_confirmations', function (Blueprint $t) {
            $t->foreignId('qso_id')->primary()->constrained('qsos')->restrictOnDelete();
            $t->foreignId('qso_match_id')->constrained()->restrictOnDelete();
        });
        Schema::create('notification_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('event_key')->unique();
            $t->string('kind');
            $t->json('payload');
            $t->string('status')->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->text('last_error')->nullable();
            $t->timestampTz('delivered_at')->nullable();
            $t->timestampTz('read_at')->nullable();
            $t->timestampsTz();
            $t->index(['user_id', 'created_at']);
        });
        Schema::create('callsign_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('callsign_id')->constrained()->restrictOnDelete();
            $t->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $t->string('status');
            $t->text('reason');
            $t->timestampTz('created_at')->useCurrent();
        });
        Schema::table('callsigns', function (Blueprint $t) {
            $t->string('reserved_callsign', 32)->nullable()->unique();
        });
        DB::table('callsigns')->update(['reserved_callsign' => DB::raw('normalized_callsign')]);
        Schema::table('callsigns', function (Blueprint $t) {
            $t->dropUnique(['normalized_callsign']);
            $t->index('normalized_callsign');
        });
    }

    public function down(): void
    {
        // La reserva liberada permite historial repetido: se requiere una corrección hacia adelante.
        throw new RuntimeException('Esta migración conserva auditoría y no admite rollback destructivo.');
    }
};
