<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('match_predictions', function (Blueprint $table) {
            $table->unsignedTinyInteger('predicted_home_score')->nullable()->after('predicted_outcome');
            $table->unsignedTinyInteger('predicted_away_score')->nullable()->after('predicted_home_score');
            $table->boolean('is_double')->default(false)->after('predicted_away_score');
        });
    }
    public function down(): void {
        Schema::table('match_predictions', function (Blueprint $table) {
            $table->dropColumn(['predicted_home_score', 'predicted_away_score', 'is_double']);
        });
    }
};
