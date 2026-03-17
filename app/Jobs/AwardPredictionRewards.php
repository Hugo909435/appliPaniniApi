<?php
namespace App\Jobs;

use App\Models\ClubMatch;
use App\Models\MatchPrediction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AwardPredictionRewards implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $matchId) {}

    public function handle(): void
    {
        $match = ClubMatch::find($this->matchId);
        if (!$match || !$match->result_outcome) return;

        // Marque les prédictions comme traitées (sans donner les coins — le joueur doit réclamer)
        MatchPrediction::where('club_match_id', $match->id)
            ->whereNull('rewarded_at')
            ->update(['rewarded_at' => now()]);
    }
}
