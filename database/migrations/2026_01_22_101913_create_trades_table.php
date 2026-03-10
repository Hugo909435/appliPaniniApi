<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['pending', 'accepted', 'cancelled'])->default('pending');
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('offered_card_id')->constrained('cards')->onDelete('cascade');
            $table->foreignId('requested_card_id')->constrained('cards')->onDelete('cascade');
            $table->foreignId('responder_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Index pour performances
            $table->index(['status', 'creator_id']);
            $table->index(['status', 'responder_id']);
            $table->index('requested_card_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
