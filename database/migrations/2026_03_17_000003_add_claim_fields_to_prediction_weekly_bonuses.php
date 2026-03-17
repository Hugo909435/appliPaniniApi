<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('prediction_weekly_bonuses', function (Blueprint $table) {
            $table->unsignedInteger('coins_earned')->default(0)->after('awarded_at');
            $table->unsignedInteger('xp_earned')->default(0)->after('coins_earned');
            $table->timestamp('claimed_at')->nullable()->after('xp_earned');
        });
    }
    public function down(): void {
        Schema::table('prediction_weekly_bonuses', function (Blueprint $table) {
            $table->dropColumn(['coins_earned', 'xp_earned', 'claimed_at']);
        });
    }
};
