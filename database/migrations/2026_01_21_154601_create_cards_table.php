<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('teams_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->foreignId('positions_id')->nullable()->constrained('positions')->onDelete('cascade');
            $table->foreignId('rarities_id')->nullable()->constrained('rarities')->onDelete('cascade');
            $table->string('image')->nullable();
            $table->unsignedInteger('attack')->default(50);
            $table->unsignedInteger('defense')->default(50);
            $table->unsignedInteger('speed')->default(50);
            $table->unsignedInteger('stamina')->default(50);
            $table->unsignedInteger('number')->nullable();
            $table->foreignId('series_id')->nullable()->constrained('series')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
