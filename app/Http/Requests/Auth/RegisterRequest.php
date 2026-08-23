<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            // Optionnel : un client qui n'envoie rien (JS désactivé, échec du
            // chargement des suggestions...) ne doit jamais bloquer
            // l'inscription — AuthController retombe alors sur un seed
            // aléatoire généré côté serveur. Simple validation de format (un
            // seed est un UUID arbitraire, jamais une donnée sensible) : pas
            // besoin de vérifier qu'il provient bien de /avatars/suggestions,
            // n'importe quel UUID valide ne fait que choisir l'apparence du
            // propre avatar de l'utilisateur qui l'envoie.
            'avatar_seed' => ['nullable', 'string', 'uuid'],
        ];
    }
}
