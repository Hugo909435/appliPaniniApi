<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rarities = [
            [
                'name'      => 'Meilleur Buteur',
                'slug'      => 'buteur',
                'color'     => '#FF8C00',
                'drop_rate' => 0.00,
                'created_at'=> now(),
                'updated_at'=> now(),
            ],
            [
                'name'      => 'Meilleur Passeur',
                'slug'      => 'passeur',
                'color'     => '#00D4FF',
                'drop_rate' => 0.00,
                'created_at'=> now(),
                'updated_at'=> now(),
            ],
            [
                'name'      => 'Meilleur Joueur',
                'slug'      => 'joueur_debut',
                'color'     => '#CC44FF',
                'drop_rate' => 0.00,
                'created_at'=> now(),
                'updated_at'=> now(),
            ],
        ];

        foreach ($rarities as $rarity) {
            $exists = DB::table('rarities')->where('slug', $rarity['slug'])->exists();
            if (!$exists) {
                DB::table('rarities')->insert($rarity);
            }
        }
    }

    public function down(): void
    {
        DB::table('rarities')->whereIn('slug', ['buteur', 'passeur', 'joueur_debut'])->delete();
    }
};
