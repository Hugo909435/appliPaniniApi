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
        Schema::table('club_teams', function (Blueprint $table) {
            if (!Schema::hasColumn('club_teams', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('name');
            }
            if (!Schema::hasColumn('club_teams', 'primary_color')) {
                $table->string('primary_color', 9)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_teams', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('club_teams', 'is_active')) {
                $columns[] = 'is_active';
            }
            if (Schema::hasColumn('club_teams', 'primary_color')) {
                $columns[] = 'primary_color';
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
