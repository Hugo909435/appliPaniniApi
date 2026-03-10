<?php

namespace Database\Seeders;

use App\Models\MoneyPackage;
use Illuminate\Database\Seeder;

class MoneyPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Pack Starter',
                'slug' => 'starter',
                'description' => 'Parfait pour commencer',
                'amount' => 100,
                'price' => 499, // 4.99€
                'bonus' => 0,
                'is_popular' => false,
            ],
            [
                'name' => 'Pack Standard',
                'slug' => 'standard',
                'description' => 'Le plus populaire',
                'amount' => 250,
                'price' => 999, // 9.99€
                'bonus' => 25, // +10%
                'is_popular' => true,
            ],
            [
                'name' => 'Pack Pro',
                'slug' => 'pro',
                'description' => 'Pour les joueurs sérieux',
                'amount' => 500,
                'price' => 1999, // 19.99€
                'bonus' => 100, // +20%
                'is_popular' => false,
            ],
            [
                'name' => 'Pack Ultimate',
                'slug' => 'ultimate',
                'description' => 'Le meilleur rapport qualité-prix !',
                'amount' => 1200,
                'price' => 4999, // 49.99€
                'bonus' => 300, // +25%
                'is_popular' => false,
            ],
        ];

        foreach ($packages as $package) {
            MoneyPackage::create($package);
        }
    }
}
