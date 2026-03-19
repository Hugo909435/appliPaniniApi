<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_teams', function (Blueprint $table) {
            if (!Schema::hasColumn('club_teams', 'is_main_club')) {
                $table->boolean('is_main_club')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_teams', function (Blueprint $table) {
            if (Schema::hasColumn('club_teams', 'is_main_club')) {
                $table->dropColumn('is_main_club');
            }
        });
    }
};
