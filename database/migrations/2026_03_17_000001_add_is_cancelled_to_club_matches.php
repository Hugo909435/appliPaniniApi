<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('club_matches', function (Blueprint $table) {
            $table->boolean('is_cancelled')->default(false)->after('result_set_at');
        });
    }
    public function down(): void {
        Schema::table('club_matches', function (Blueprint $table) {
            $table->dropColumn('is_cancelled');
        });
    }
};
