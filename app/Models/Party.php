<?php

namespace App\Models;

use App\Enums\LanguageCommunity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'abbreviation', 'logo_url', 'color_hex', 'description', 'slogan', 'language_community',
    'is_active', 'ideological_x', 'ideological_y',
])]
class Party extends Model
{
    protected function casts(): array
    {
        return [
            'language_community' => LanguageCommunity::class,
            'is_active' => 'boolean',
            'ideological_x' => 'decimal:2',
            'ideological_y' => 'decimal:2',
        ];
    }

    public function partyPositions(): HasMany
    {
        return $this->hasMany(PartyPosition::class);
    }

    public function resultPartyScores(): HasMany
    {
        return $this->hasMany(ResultPartyScore::class);
    }
}
