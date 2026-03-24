<?php

namespace Database\Seeders;

use App\Models\ClubTeam;
use App\Models\Pack;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PackSeeder extends Seeder
{
    public function run(): void
    {
        Pack::query()->delete();

        $clubs = ClubTeam::where('is_main_club', true)->get();
        foreach ($clubs as $club) {
            Pack::create([
                'name' => "Pack {$club->short_name}",
                'slug' => Str::slug("pack-{$club->short_name}"),
                'description' => "Pack officiel de {$club->name}",
                'price' => 50,
                'card_count' => 5,
                'image' => null,
                'is_active' => true,
                'club_team_id' => $club->id,
            ]);
        }
    }
}
