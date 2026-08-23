<?php

namespace App\Console\Commands;

use App\Models\Party;
use App\Services\PoliticalAxisCalculator;
use Illuminate\Console\Command;

class CalculatePartyIdeologicalAxes extends Command
{
    protected $signature = 'party:calculate-axes';

    protected $description = "Recalcule et sauvegarde la position idéologique (ideological_x/y) de chaque parti à partir de party_positions — à relancer après toute mise à jour du contenu politique par l'expert (cf. cahier des charges §6.4, veille sur les évolutions politiques).";

    public function __construct(private readonly PoliticalAxisCalculator $calculator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $parties = Party::with('partyPositions.question')->get();

        foreach ($parties as $party) {
            $axes = $this->calculator->calculateForParty($party->partyPositions);
            $party->update([
                'ideological_x' => $axes['axis_x'],
                'ideological_y' => $axes['axis_y'],
            ]);
        }

        $this->info("Position idéologique recalculée pour {$parties->count()} parti(s).");

        return self::SUCCESS;
    }
}
