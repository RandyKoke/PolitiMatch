<?php

namespace App\Services;

use App\Models\GuestSession;
use App\Models\QuizResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AccountMigrationService
{
    /**
     * Rattache les données d'une session anonyme (Guest Flow) à un compte
     * fraîchement créé ou authentifié.
     *
     * Volontairement séparée de la création du compte : un échec ici ne doit
     * jamais faire perdre le compte qui vient d'être créé, donc on ne lève
     * jamais d'exception, on renvoie toujours un résultat dégradé exploitable
     * par l'appelant (cf. diagramme de séquence Guest Flow et diagramme
     * d'activité Inscription/Migration).
     *
     * @return array{migrated: bool, reason: string|null, results_count: int|null}
     */
    public function migrate(?string $sessionToken, User $user): array
    {
        if ($sessionToken === null || $sessionToken === '') {
            return ['migrated' => false, 'reason' => 'no_session_token', 'results_count' => null];
        }

        // PostgreSQL rejette une valeur non conforme au type natif UUID avec
        // une erreur SQL (pas de résultat vide) : un en-tête client malformé
        // planterait la requête sans ce garde-fou en amont.
        if (! Str::isUuid($sessionToken)) {
            return ['migrated' => false, 'reason' => 'session_not_found', 'results_count' => null];
        }

        $guestSession = GuestSession::where('session_token', $sessionToken)->first();

        if ($guestSession === null) {
            return ['migrated' => false, 'reason' => 'session_not_found', 'results_count' => null];
        }

        if ($guestSession->migrated_at !== null) {
            return ['migrated' => false, 'reason' => 'session_already_migrated', 'results_count' => null];
        }

        if ($guestSession->expires_at->isPast()) {
            return ['migrated' => false, 'reason' => 'session_expired', 'results_count' => null];
        }

        try {
            $resultsCount = DB::transaction(function () use ($guestSession, $user) {
                $affected = QuizResult::where('session_token', $guestSession->session_token)
                    ->whereNull('user_id')
                    ->update(['user_id' => $user->id]);

                $guestSession->update([
                    'user_id' => $user->id,
                    'migrated_at' => now(),
                ]);

                return $affected;
            });
        } catch (Throwable $e) {
            report($e);

            return ['migrated' => false, 'reason' => 'migration_failed', 'results_count' => null];
        }

        return ['migrated' => true, 'reason' => null, 'results_count' => $resultsCount];
    }
}
