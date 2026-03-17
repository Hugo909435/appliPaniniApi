<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('badges')) {
            return;
        }
        DB::table('badges')->update([
            'image' => '/assets/badge/lrvf.png',
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('badges')) {
            return;
        }
        DB::table('badges')
            ->where('image', '/assets/badge/lrvf.png')
            ->update(['image' => null]);
    }
};
