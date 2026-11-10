<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('preferred_language', 10)->default('es');
            $table->string('timezone')->default('UTC');
            $table->char('country_code', 2)->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_admin')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();
        });
        Schema::create('callsigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('callsign', 32);
            $table->string('normalized_callsign', 32)->unique();
            $table->char('country_code', 2)->nullable();
            $table->string('verification_status')->default('pending');
            $table->boolean('is_primary')->default(false);
            $table->timestampTz('verified_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'user_id']);
        });
        Schema::create('qsos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('callsign_id');
            // La FK compuesta impide registrar contactos con un indicativo ajeno.
            $table->foreign(['callsign_id', 'user_id'])->references(['id', 'user_id'])->on('callsigns')->restrictOnDelete();
            $table->string('contacted_callsign', 32);
            $table->string('normalized_contacted_callsign', 32);
            $table->decimal('frequency_khz', 12, 3);
            $table->string('band', 16);
            $table->string('mode', 16);
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->string('rst_sent', 10)->nullable();
            $table->string('rst_received', 10)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->timestampsTz();
            $table->index(['normalized_contacted_callsign', 'band', 'mode', 'started_at'], 'qsos_matching_index');
            $table->index(['user_id', 'started_at']);
        });
        Schema::create('qso_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qso_a_id')->constrained('qsos')->restrictOnDelete();
            $table->foreignId('qso_b_id')->constrained('qsos')->restrictOnDelete();
            $table->unsignedInteger('time_difference_seconds');
            $table->string('status')->default('candidate');
            $table->timestampTz('matched_at');
            $table->timestampTz('a_confirmed_at')->nullable();
            $table->timestampTz('b_confirmed_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['qso_a_id', 'qso_b_id']);
        });
        Schema::create('qsl_cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('qso_match_id')->constrained()->restrictOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('receiver_user_id')->constrained('users')->restrictOnDelete();
            $table->string('template')->default('default');
            $table->string('file_path')->nullable();
            $table->string('status')->default('pending');
            $table->timestampTz('generated_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();
            $table->unique(['qso_match_id', 'sender_user_id']);
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestampTz('read_at')->nullable();
            $table->string('delivery_status')->default('pending');
            $table->text('delivery_error')->nullable();
            $table->timestampsTz();
        });
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->ipAddress('ip_address')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
        // Orden canónico: evita pares inversos y autorreferencias incluso bajo concurrencia.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE qso_matches ADD CONSTRAINT ordered_qsos CHECK (qso_a_id < qso_b_id)');
            DB::statement('ALTER TABLE qsos ADD CONSTRAINT positive_frequency CHECK (frequency_khz > 0)');
            DB::statement('ALTER TABLE qsos ADD CONSTRAINT ordered_times CHECK (ended_at IS NULL OR ended_at >= started_at)');
            DB::statement('CREATE UNIQUE INDEX callsigns_one_primary ON callsigns (user_id) WHERE is_primary = true');
        }
    }

    public function down(): void
    {
        foreach (['security_events', 'notifications', 'qsl_cards', 'qso_matches', 'qsos', 'callsigns'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['preferred_language', 'timezone', 'country_code', 'status', 'is_admin', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
