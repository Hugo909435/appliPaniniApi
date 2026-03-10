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
        Schema::create('badge_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('responder_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('offered_badge_id')->constrained('badges')->onDelete('cascade');
            $table->foreignId('requested_badge_id')->constrained('badges')->onDelete('cascade');
            $table->enum('status', ['pending', 'accepted', 'cancelled'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Index pour les recherches
            $table->index('status');
            $table->index(['creator_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badge_trades');
    }
};
