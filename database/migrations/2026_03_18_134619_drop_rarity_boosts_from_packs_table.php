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
        Schema::table('packs', function (Blueprint $table) {
            $table->dropColumn('rarity_boosts');
        });
    }

    public function down(): void
    {
        Schema::table('packs', function (Blueprint $table) {
            $table->json('rarity_boosts')->nullable();
        });
    }
};
