<?php

namespace App\Repositories;

use App\Models\Party;
use Illuminate\Database\Eloquent\Collection;

class PartyRepository
{
    public function listActive(): Collection
    {
        return Party::where('is_active', true)
            ->orderBy('name')
            ->get([
                'id', 'name', 'abbreviation', 'color_hex', 'logo_url', 'description', 'slogan',
                'language_community', 'ideological_x', 'ideological_y',
            ]);
    }
}
