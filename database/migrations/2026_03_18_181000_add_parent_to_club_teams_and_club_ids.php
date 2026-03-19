<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_teams', function (Blueprint $table) {
            if (!Schema::hasColumn('club_teams', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('id')->constrained('club_teams')->nullOnDelete();
            }
        });

        Schema::table('packs', function (Blueprint $table) {
            if (!Schema::hasColumn('packs', 'club_team_id')) {
                $table->foreignId('club_team_id')->nullable()->after('card_count')->constrained('club_teams')->nullOnDelete();
            }
        });

        Schema::table('trades', function (Blueprint $table) {
            if (!Schema::hasColumn('trades', 'club_team_id')) {
                $table->foreignId('club_team_id')->nullable()->after('receiver_id')->constrained('club_teams')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            if (Schema::hasColumn('trades', 'club_team_id')) {
                $table->dropForeign(['club_team_id']);
                $table->dropColumn('club_team_id');
            }
        });

        Schema::table('packs', function (Blueprint $table) {
            if (Schema::hasColumn('packs', 'club_team_id')) {
                $table->dropForeign(['club_team_id']);
                $table->dropColumn('club_team_id');
            }
        });

        Schema::table('club_teams', function (Blueprint $table) {
            if (Schema::hasColumn('club_teams', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            }
        });
    }
};
