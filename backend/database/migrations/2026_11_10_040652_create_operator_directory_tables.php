<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('callsign_id')->unique();
            $t->foreign(['callsign_id', 'user_id'])->references(['id', 'user_id'])->on('callsigns')->restrictOnDelete();
            $t->text('biography')->nullable();
            $t->string('photo_path')->nullable();
            $t->char('country_code', 2)->nullable();
            $t->json('links')->nullable();
            $t->boolean('is_public')->default(false);
            $t->boolean('show_qsl_history')->default(false);
            $t->timestampsTz();
        });
        Schema::create('operator_follows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('followed_id')->constrained('users')->cascadeOnDelete();
            $t->unique(['follower_id', 'followed_id']);
            $t->index('followed_id');
            $t->timestampsTz();
        });
        Schema::create('operator_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->text('content');
            $t->timestampsTz();
            $t->index(['user_id', 'created_at']);
        });
        Schema::create('ownership_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('callsign_id');
            $t->foreign(['callsign_id', 'user_id'])->references(['id', 'user_id'])->on('callsigns')->restrictOnDelete();
            $t->text('evidence');
            $t->string('status')->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('reviewed_at')->nullable();
            $t->timestampsTz();
        });
        DB::statement("CREATE UNIQUE INDEX ownership_one_pending ON ownership_requests (callsign_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        foreach (['ownership_requests', 'operator_posts', 'operator_follows', 'operator_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
