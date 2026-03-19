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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'club_team_id')) {
                $table->unsignedBigInteger('club_team_id')->nullable()->after('is_super_admin');
                $table->foreign('club_team_id')->references('id')->on('club_teams')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'club_team_id')) {
                $table->dropForeign(['club_team_id']);
                $table->dropColumn('club_team_id');
            }
        });
    }
};
