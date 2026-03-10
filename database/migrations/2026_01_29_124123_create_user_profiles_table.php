<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Carte de profil personnalisable
            $table->foreignId('featured_badge_id')->nullable()->constrained('badges')->onDelete('set null');
            $table->foreignId('favorite_player_card_id')->nullable()->constrained('cards')->onDelete('set null');
            $table->foreignId('favorite_team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->string('collector_nickname', 50)->nullable();
            $table->string('bio', 100)->nullable();
            $table->string('profile_theme')->default('classic'); // classic, neon, gold, diamond, team
            $table->string('profile_frame')->default('default'); // default, bronze, silver, gold, legendary

            // Vitrine (showcase) - Array de card_ids
            $table->json('showcase_card_ids')->nullable();

            // Stats et progression
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('experience')->default(0);
            $table->unsignedInteger('total_packs_opened')->default(0);
            $table->unsignedInteger('total_trades_completed')->default(0);
            $table->unsignedInteger('login_streak')->default(0);
            $table->timestamp('last_login')->nullable();

            // Titre actif (on garde la structure pour plus tard)
            $table->string('active_title')->nullable();

            // Privacy settings
            $table->boolean('profile_public')->default(true);
            $table->boolean('show_collection')->default(true);
            $table->boolean('show_trades')->default(true);
            $table->boolean('show_stats')->default(true);

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
