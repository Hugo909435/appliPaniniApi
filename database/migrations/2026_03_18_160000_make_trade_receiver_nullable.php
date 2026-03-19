<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropForeign(['receiver_id']);
            $table->unsignedBigInteger('receiver_id')->nullable()->change();
            $table->foreign('receiver_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('trade_items', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->unsignedBigInteger('owner_id')->nullable()->change();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropForeign(['receiver_id']);
            $table->unsignedBigInteger('receiver_id')->nullable(false)->change();
            $table->foreign('receiver_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('trade_items', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->unsignedBigInteger('owner_id')->nullable(false)->change();
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
