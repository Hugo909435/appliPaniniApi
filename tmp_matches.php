<?php
require __DIR__.'/vendor/autoload.php';
$app=require __DIR__.'/bootstrap/app.php';
$kernel=$app->make(Illuminate\Contracts\Console\Kernel::class);$kernel->bootstrap();
$rows=DB::table('club_matches')->select('id','club_team_id','opponent_name','is_home','home_score','away_score','result_outcome')->get();
foreach($rows as $r){echo "$r->id\tclub=$r->club_team_id\tvs $r->opponent_name\thome?=$r->is_home\tscore=$r->home_score-$r->away_score\tres=$r->result_outcome\n";}
