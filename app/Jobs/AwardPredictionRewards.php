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

    public function __construct(public int $matchId)
    {
    }

    public function handle(): void
    {
        $match = ClubMatch::find($this->matchId);
        if (!$match || !$match->result_outcome) return;

        MatchPrediction::where('club_match_id', $match->id)
            ->where('predicted_outcome', $match->result_outcome)
            ->whereNull('rewarded_at')
            ->chunkById(100, function ($predictions) {
                foreach ($predictions as $prediction) {
                    $prediction->user()->increment('coins', 50);
                    $prediction->update(['rewarded_at' => now()]);
                }
            });
    }
}
