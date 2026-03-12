<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('match_weeks', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('number');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('label')->nullable();
            $table->timestamps();
            $table->unique('number');
        });
    }
    public function down(): void { Schema::dropIfExists('match_weeks'); }
};
