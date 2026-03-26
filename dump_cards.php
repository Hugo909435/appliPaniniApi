<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();
$cards = \App\Models\Card::orderBy('id', 'desc')->take(5)->get(['id','name','club_team_id','pack_id','created_at']);
echo json_encode($cards, JSON_PRETTY_PRINT), "\n";
?>
