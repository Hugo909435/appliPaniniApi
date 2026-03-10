<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('level')->default(1)->after('money');
        });

        // Backfill levels from profiles when available (portable across DBs)
        DB::table('user_profiles')
            ->select('user_id', 'level')
            ->orderBy('user_id')
            ->chunk(500, function ($profiles) {
                foreach ($profiles as $profile) {
                    DB::table('users')
                        ->where('id', $profile->user_id)
                        ->update(['level' => $profile->level ?? 1]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
