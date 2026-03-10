<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('permanent'); // permanent, timed, daily
            $table->string('metric'); // login_streak, packs_opened, trades_completed
            $table->unsignedInteger('target')->default(1);
            $table->unsignedInteger('reward_xp')->default(0);
            $table->unsignedInteger('reward_coins')->default(0);
            $table->foreignId('reward_badge_id')->nullable()->constrained('badges')->nullOnDelete();
            $table->foreignId('reward_card_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->unsignedInteger('reward_card_quantity')->default(1);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
