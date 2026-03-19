<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_teams', function (Blueprint $table) {
            if (!Schema::hasColumn('club_teams', 'logo')) {
                $table->string('logo')->nullable()->after('short_name');
            }
            if (!Schema::hasColumn('club_teams', 'primary_color')) {
                $table->string('primary_color', 9)->nullable()->after('logo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_teams', function (Blueprint $table) {
            if (Schema::hasColumn('club_teams', 'logo')) {
                $table->dropColumn('logo');
            }
            if (Schema::hasColumn('club_teams', 'primary_color')) {
                $table->dropColumn('primary_color');
            }
        });
    }
};
