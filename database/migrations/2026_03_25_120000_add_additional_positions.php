<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $newPositions = ['Président', 'Supporter', 'Bénévole'];

    public function up(): void
    {
        foreach ($this->newPositions as $name) {
            $exists = DB::table('positions')->where('name', $name)->exists();

            if (!$exists) {
                DB::table('positions')->insert([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('positions')->whereIn('name', $this->newPositions)->delete();
    }
};
