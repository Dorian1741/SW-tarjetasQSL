<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_credentials', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('callsign_id');
            $t->foreign(['callsign_id', 'user_id'])->references(['id', 'user_id'])->on('callsigns')->restrictOnDelete();
            $t->string('name', 100);
            $t->char('token_hash', 64);
            $t->char('security_fingerprint', 64);
            $t->json('abilities');
            $t->timestampTz('expires_at');
            $t->timestampTz('revoked_at')->nullable();
            $t->timestampTz('last_used_at')->nullable();
            $t->timestampsTz();
            $t->index('user_id');
        });
        Schema::create('integration_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('integration_credential_id')->constrained()->restrictOnDelete();
            $t->string('external_id', 100);
            $t->char('request_hash', 64);
            $t->foreignId('qso_id')->unique()->constrained()->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['integration_credential_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_receipts');
        Schema::dropIfExists('integration_credentials');
    }
};
