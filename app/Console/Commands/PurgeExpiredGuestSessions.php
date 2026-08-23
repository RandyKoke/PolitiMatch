<?php

namespace App\Console\Commands;

use App\Models\GuestSession;
use Illuminate\Console\Command;

class PurgeExpiredGuestSessions extends Command
{
    protected $signature = 'guest-sessions:purge-expired';

    protected $description = "Supprime les sessions invitées expirées et jamais migrées vers un compte (cahier des charges §5.2)";

    /**
     * Ne touche jamais une session encore valide (expires_at dans le futur,
     * quel que soit son âge) ni une session déjà migrée (migrated_at non
     * nul) : ces deux garde-fous sont dans la requête elle-même, pas de la
     * logique applicative séparée qui pourrait diverger.
     *
     * La suppression du GuestSession suffit : quiz_results.session_token
     * référence guest_sessions.session_token avec cascadeOnDelete
     * (migration create_quiz_results_table), elle-même en cascade vers
     * answers/result_party_scores (cascadeOnDelete sur quiz_result_id) —
     * PostgreSQL se charge de toute la chaîne, aucune suppression manuelle
     * de ces tables ici.
     */
    public function handle(): int
    {
        $count = GuestSession::whereNull('migrated_at')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("{$count} session(s) invitée(s) expirée(s) purgée(s).");

        return self::SUCCESS;
    }
}
