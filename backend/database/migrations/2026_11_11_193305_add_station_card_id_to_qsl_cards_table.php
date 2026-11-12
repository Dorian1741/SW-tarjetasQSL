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
        Schema::table('qsl_cards', function (Blueprint $table) {
            $table->foreignId('station_card_id')->nullable()->constrained('station_cards')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qsl_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('station_card_id');
        });
    }
};
