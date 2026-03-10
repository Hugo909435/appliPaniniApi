<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('user_profiles', 'featured_badge_id')) {
            Schema::table('user_profiles', function (Blueprint $table) {
                $table->dropConstrainedForeignId('featured_badge_id');
            });
        }

        if (Schema::hasColumn('challenges', 'reward_badge_id')) {
            Schema::table('challenges', function (Blueprint $table) {
                $table->dropConstrainedForeignId('reward_badge_id');
            });
        }

        Schema::dropIfExists('badge_trades');
        Schema::dropIfExists('badge_user');
        Schema::dropIfExists('badge_packs');
        Schema::dropIfExists('badges');
    }

    public function down(): void
    {
        // Non r?versible : r?introduire les badges via d'anciennes migrations si besoin.
    }
};
