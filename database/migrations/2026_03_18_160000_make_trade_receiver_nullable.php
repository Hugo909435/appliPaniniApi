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
        \DB::table('trades')->whereNull('receiver_id')->delete();
        \DB::table('trade_items')->whereNull('owner_id')->delete();

        // Drop FKs with SET NULL before making columns NOT NULL
        \DB::statement('ALTER TABLE trades DROP FOREIGN KEY IF EXISTS trades_receiver_id_foreign');
        \DB::statement('ALTER TABLE trade_items DROP FOREIGN KEY IF EXISTS trade_items_owner_id_foreign');

        Schema::table('trades', function (Blueprint $table) {
            $table->unsignedBigInteger('receiver_id')->nullable(false)->change();
            $table->foreign('receiver_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('trade_items', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable(false)->change();
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
