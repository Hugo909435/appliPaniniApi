<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->foreignId('reward_medal_id')
                ->nullable()
                ->after('reward_coins')
                ->constrained('medals')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reward_medal_id');
        });
    }
};
