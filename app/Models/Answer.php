<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quiz_result_id', 'question_id', 'user_score', 'was_skipped', 'answered_at'])]
class Answer extends Model
{
    // Colonne answered_at dédiée : pas de created_at/updated_at sur cette table.
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'user_score' => 'integer',
            'was_skipped' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function quizResult(): BelongsTo
    {
        return $this->belongsTo(QuizResult::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
