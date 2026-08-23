<?php

namespace App\Console\Commands;

use App\Enums\QuizResultStatus;
use App\Models\QuizResult;
use Illuminate\Console\Command;

class CleanupStaleComputingQuizzes extends Command
{
    protected $signature = 'quiz:cleanup-stale-computing';

    protected $description = "Marque comme 'failed' les quiz bloqués en 'computing' depuis plus de 10 minutes";

    /**
     * Filet de sécurité si l'UPDATE best-effort de la Phase 5 (persistResults
     * en échec) échoue lui-même — cf. spec technique du matching §4.
     */
    public function handle(): int
    {
        $count = QuizResult::where('status', QuizResultStatus::Computing)
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update(['status' => QuizResultStatus::Failed]);

        $this->info("{$count} quiz marqué(s) comme échoué(s) après blocage en calcul.");

        return self::SUCCESS;
    }
}
