<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('club_matches', function (Blueprint $table) {
            $table->foreignId('match_week_id')->nullable()->after('id')
                ->constrained('match_weeks')->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('club_matches', function (Blueprint $table) {
            $table->dropForeign(['match_week_id']);
            $table->dropColumn('match_week_id');
        });
    }
};
