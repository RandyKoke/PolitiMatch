<?php

namespace App\Models;

use App\Enums\AxisType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['theme_id', 'label', 'explanation', 'weight', 'axe_ideologique', 'is_active', 'position_order'])]
class Question extends Model
{
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'axe_ideologique' => AxisType::class,
            'is_active' => 'boolean',
            'position_order' => 'integer',
        ];
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function partyPositions(): HasMany
    {
        return $this->hasMany(PartyPosition::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    /**
     * Questions affichées dans le quiz, dans leur ordre d'affichage.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position_order');
    }
}
