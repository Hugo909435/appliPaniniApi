<?php
require __DIR__.'/vendor/autoload.php';
$app=require __DIR__.'/bootstrap/app.php';
$kernel=$app->make(Illuminate\Contracts\Console\Kernel::class);$kernel->bootstrap();
$clubId=App\Models\ClubTeam::where('is_main_club',true)->value('id');
$clubIds=App\Models\ClubTeam::where('id',$clubId)->orWhere('parent_id',$clubId)->pluck('id');
$data = DB::table('match_predictions')
 ->join('club_matches','club_matches.id','=','match_predictions.club_match_id')
 ->join('club_teams','club_teams.id','=','club_matches.club_team_id')
 ->whereIn('club_matches.club_team_id',$clubIds)
 ->selectRaw("club_matches.id as match_id, CASE WHEN club_matches.is_home = 1 THEN club_teams.name ELSE club_matches.opponent_name END as home_name, CASE WHEN club_matches.is_home = 1 THEN club_matches.opponent_name ELSE club_teams.name END as away_name, COUNT(*) as total")
 ->groupBy('match_id','home_name','away_name')
 ->get();
foreach($data as $d){echo json_encode($d),"\n";}
