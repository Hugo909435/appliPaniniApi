<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_match_id')->constrained('club_matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('predicted_outcome'); // home | draw | away
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            $table->unique(['club_match_id', 'user_id']);
            $table->index(['user_id', 'club_match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_predictions');
    }
};
