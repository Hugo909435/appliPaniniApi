<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairLockedCards extends Command
{
    protected $signature   = 'trades:repair-locks {--dry-run : Affiche les cartes orphelines sans les corriger}';
    protected $description = 'Réinitialise les verrous orphelins sur user_cards (is_locked=true sans trade pending/accepted associé)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Trouve toutes les cartes verrouillées dont aucun trade_item actif ne les réclame.
        // Un verrou est "orphelin" si la carte est locked mais qu'il n'existe aucun
        // trade_item rattaché à un trade en statut pending ou accepted.
        $orphans = DB::table('user_cards as uc')
            ->where('uc.is_locked', true)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('trade_items as ti')
                    ->join('trades as t', 't.id', '=', 'ti.trade_id')
                    ->whereColumn('ti.owner_id', 'uc.user_id')
                    ->whereColumn('ti.card_id', 'uc.card_id')
                    ->whereIn('t.status', ['pending', 'accepted']);
            })
            ->select('uc.user_id', 'uc.card_id')
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('Aucun verrou orphelin trouvé.');
            return self::SUCCESS;
        }

        $this->warn("Verrous orphelins trouvés : {$orphans->count()}");

        $headers = ['user_id', 'card_id'];
        $rows    = $orphans->map(fn($r) => [$r->user_id, $r->card_id])->toArray();
        $this->table($headers, $rows);

        if ($dryRun) {
            $this->info('Mode dry-run — aucune modification effectuée.');
            return self::SUCCESS;
        }

        if (!$this->confirm("Réinitialiser ces {$orphans->count()} verrous ?", true)) {
            $this->info('Opération annulée.');
            return self::SUCCESS;
        }

        $fixed = 0;
        foreach ($orphans as $row) {
            DB::table('user_cards')
                ->where('user_id', $row->user_id)
                ->where('card_id', $row->card_id)
                ->update(['is_locked' => false]);
            $fixed++;
        }

        $this->info("Réparation terminée : {$fixed} verrou(s) réinitialisé(s).");
        return self::SUCCESS;
    }
}
