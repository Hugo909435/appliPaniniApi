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
        // Guard in case Cashier tables are not installed in local dev.
        if (Schema::hasTable('subscription_items')) {
            Schema::table('subscription_items', function (Blueprint $table) {
                $table->string('meter_event_name')->nullable()->after('quantity');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('subscription_items')) {
            Schema::table('subscription_items', function (Blueprint $table) {
                $table->dropColumn('meter_event_name');
            });
        }
    }
};
