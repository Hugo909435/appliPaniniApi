<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('favorite_team_id');
        });

        Schema::dropIfExists('teams');
    }

    public function down(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->timestamps();
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->foreignId('favorite_team_id')->nullable()->constrained('teams')->onDelete('set null');
        });
    }
};
