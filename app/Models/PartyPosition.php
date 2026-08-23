<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['party_id', 'question_id', 'score', 'justification', 'source_reference', 'validated_by_expert'])]
class PartyPosition extends Model
{
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'validated_by_expert' => 'boolean',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
