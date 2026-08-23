<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quiz_result_id', 'party_id', 'compatibility_score', 'rank', 'calculated_at'])]
class ResultPartyScore extends Model
{
    // Colonne calculated_at dédiée : pas de created_at/updated_at sur cette table.
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'compatibility_score' => 'decimal:2',
            'rank' => 'integer',
            'calculated_at' => 'datetime',
        ];
    }

    public function quizResult(): BelongsTo
    {
        return $this->belongsTo(QuizResult::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
