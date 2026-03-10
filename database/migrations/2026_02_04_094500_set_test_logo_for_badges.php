<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('badges')->update([
            'image' => '/assets/badge/lrvf.png',
        ]);
    }

    public function down(): void
    {
        DB::table('badges')
            ->where('image', '/assets/badge/lrvf.png')
            ->update(['image' => null]);
    }
};
