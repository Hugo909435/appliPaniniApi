<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('user_profiles', 'collector_nickname')) {
                $table->dropColumn('collector_nickname');
            }
            if (Schema::hasColumn('user_profiles', 'level')) {
                $table->dropColumn('level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('user_profiles', 'collector_nickname')) {
                $table->string('collector_nickname', 50)->nullable();
            }
            if (!Schema::hasColumn('user_profiles', 'level')) {
                $table->unsignedInteger('level')->default(1);
            }
        });
    }
};
