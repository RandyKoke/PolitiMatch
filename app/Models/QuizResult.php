<?php

namespace App\Models;

use App\Enums\QuizResultStatus;
use App\Services\QuizReliabilityService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'user_id', 'session_token', 'status', 'completed_at', 'share_token',
    'is_shared', 'political_axis_x', 'political_axis_y',
    'profile_label', 'profile_description',
])]
class QuizResult extends Model
{
    // updated_at ajouté par migration : nécessaire au cron de nettoyage des
    // quiz bloqués en 'computing' (cf. spec technique du matching).

    protected static function booted(): void
    {
        static::creating(function (self $result): void {
            $result->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => QuizResultStatus::class,
            'completed_at' => 'datetime',
            'is_shared' => 'boolean',
            'political_axis_x' => 'decimal:2',
            'political_axis_y' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guestSession(): BelongsTo
    {
        return $this->belongsTo(GuestSession::class, 'session_token', 'session_token');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function resultPartyScores(): HasMany
    {
        return $this->hasMany(ResultPartyScore::class);
    }

    /**
     * Charge en une fois toutes les données nécessaires au MatchingService
     * et à l'affichage des résultats, pour éviter le problème N+1.
     */
    public function scopeWithFullQuizData(Builder $query): Builder
    {
        return $query->with(['answers.question', 'resultPartyScores.party']);
    }

    /**
     * Forme de réponse JSON partagée par ResultController::show (accès
     * privé par possession de l'UUID) et ShareController::show (accès
     * public par share_token) : un seul endroit qui décide de ce qui est
     * exposé pour un résultat, pour que les deux ne divergent jamais
     * silencieusement l'un de l'autre. Suppose `answers` et
     * `resultPartyScores.party` déjà eager-loadés (scopeWithFullQuizData)
     * par l'appelant.
     *
     * `reliability` (état + compteurs) est calculé ici, une seule fois,
     * pour ResultController ET ShareController, jamais
     * recalculé différemment par l'un ou l'autre. En état bloquant (Empty/
     * TooFew), classement des partis et axes de positionnement sont
     * omis (jamais un classement construit sur un signal jugé nous-mêmes
     * insuffisant) — le frontend n'a alors même pas besoin de vérifier
     * `reliability.state` pour décider de ne pas les afficher.
     *
     * @return array<string, mixed>
     */
    public function toResultPayload(QuizReliabilityService $reliability): array
    {
        $reliabilityData = $reliability->evaluate($this->answers);
        $blocked = $reliabilityData['state']->isBlocked();

        return [
            'quiz_uuid' => $this->uuid,
            'completed_at' => $this->completed_at,
            'political_axis_x' => $blocked ? null : $this->political_axis_x,
            'political_axis_y' => $blocked ? null : $this->political_axis_y,
            'profile_label' => $this->profile_label,
            'profile_description' => $this->profile_description,
            'party_scores' => $blocked ? [] : $this->resultPartyScores->sortBy('rank')->values(),
            'reliability' => $reliabilityData,
        ];
    }
}
