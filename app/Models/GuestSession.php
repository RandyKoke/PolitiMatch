<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['session_token', 'user_id', 'migrated_at', 'expires_at'])]
class GuestSession extends Model
{
    // Table sans colonne updated_at (cf. migration).
    const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            $session->session_token ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'migrated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Résultats rattachés à cette session anonyme (via session_token, pas id).
     */
    public function quizResults(): HasMany
    {
        return $this->hasMany(QuizResult::class, 'session_token', 'session_token');
    }
}
