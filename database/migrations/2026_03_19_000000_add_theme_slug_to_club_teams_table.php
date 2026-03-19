<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_teams', function (Blueprint $table) {
            $table->string('theme_slug', 20)->default('default')->after('primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('club_teams', function (Blueprint $table) {
            $table->dropColumn('theme_slug');
        });
    }
};
