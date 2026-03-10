<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->foreignId('medal_slot_1_id')->nullable()->after('featured_badge_id')->constrained('medals')->nullOnDelete();
            $table->foreignId('medal_slot_2_id')->nullable()->after('medal_slot_1_id')->constrained('medals')->nullOnDelete();
            $table->foreignId('medal_slot_3_id')->nullable()->after('medal_slot_2_id')->constrained('medals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('medal_slot_3_id');
            $table->dropConstrainedForeignId('medal_slot_2_id');
            $table->dropConstrainedForeignId('medal_slot_1_id');
        });
    }
};
