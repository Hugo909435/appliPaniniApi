<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;
$cards = DB::table('cards')->where('name','Hugo')->orderByDesc('id')->limit(3)->get();
foreach($cards as $c){
  echo "card {$c->id} club_team_id={$c->club_team_id} pack_id={$c->pack_id}\n";
}
$teams = DB::table('club_teams')->select('id','name','short_name','parent_id')->orderBy('id')->get();
foreach($teams as $t){
  echo "team {$t->id} name={$t->name} short={$t->short_name} parent={$t->parent_id}\n";
}
?>
