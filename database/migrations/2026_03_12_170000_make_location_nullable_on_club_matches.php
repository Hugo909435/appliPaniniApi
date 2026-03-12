<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE club_matches MODIFY location VARCHAR(255) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE club_matches MODIFY location VARCHAR(255) NOT NULL");
    }
};
