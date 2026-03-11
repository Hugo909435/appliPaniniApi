<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_team_id')->constrained('club_teams')->cascadeOnDelete();
            $table->string('opponent_name');
            $table->string('location');
            $table->dateTime('kickoff_at');
            $table->boolean('is_home')->default(true);
            $table->unsignedTinyInteger('home_score')->nullable();
            $table->unsignedTinyInteger('away_score')->nullable();
            $table->string('result_outcome')->nullable(); // home | draw | away
            $table->timestamp('result_set_at')->nullable();
            $table->timestamps();

            $table->index('kickoff_at');
            $table->index(['club_team_id', 'kickoff_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_matches');
    }
};
