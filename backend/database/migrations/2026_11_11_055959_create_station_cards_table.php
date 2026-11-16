<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('station_cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('callsign_id');
            $table->foreign(['callsign_id', 'user_id'])->references(['id', 'user_id'])->on('callsigns')->restrictOnDelete();
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->string('source', 10);
            $table->string('file_path');
            $table->json('design')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('station_cards');
    }
};
