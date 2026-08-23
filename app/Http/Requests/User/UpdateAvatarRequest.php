<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'authentification elle-même est déjà garantie par le middleware
        // auth:sanctum sur la route (cf. routes/api.php) — rien de plus à
        // vérifier ici, un utilisateur connecté ne peut modifier que sa
        // propre ressource (Auth::user(), jamais un id fourni par le client).
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Même règle de format que RegisterRequest::avatar_seed, mais
            // requise ici : contrairement à l'inscription, ce endpoint n'a
            // pas de repli à choisir (l'utilisateur a explicitement demandé
            // à changer son avatar), donc pas de valeur pour laquelle
            // accepter l'absence de seed.
            'avatar_seed' => ['required', 'string', 'uuid'],
        ];
    }
}
