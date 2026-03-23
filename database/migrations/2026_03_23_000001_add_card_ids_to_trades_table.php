<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->unsignedBigInteger('offered_card_id')->nullable()->after('club_team_id');
            $table->unsignedBigInteger('requested_card_id')->nullable()->after('offered_card_id');

            $table->foreign('offered_card_id')->references('id')->on('cards')->nullOnDelete();
            $table->foreign('requested_card_id')->references('id')->on('cards')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropForeign(['offered_card_id']);
            $table->dropForeign(['requested_card_id']);
            $table->dropColumn(['offered_card_id', 'requested_card_id']);
        });
    }
};
