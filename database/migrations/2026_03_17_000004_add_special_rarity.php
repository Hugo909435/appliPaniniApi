<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('rarities')->where('slug', 'special')->exists();
        if (!$exists) {
            DB::table('rarities')->insert([
                'name' => 'Special',
                'slug' => 'special',
                'color' => '#22D3EE',
                'drop_rate' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('rarities')->where('slug', 'special')->delete();
    }
};
