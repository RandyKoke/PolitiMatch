<?php

namespace App\Http\Requests\Quiz;

use App\Http\Controllers\QuizController;
use Illuminate\Foundation\Http\FormRequest;

class StartQuizRequest extends FormRequest
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
            // 'accepted' : true, 1, "1", "yes", "on" seulement, jamais une
            // simple présence du champ — un consentement RGPD Art. 9 doit
            // être un acte positif et non équivoque, jamais présumé.
            'consent' => ['required', 'accepted'],
            // Doit correspondre exactement au texte actuellement affiché
            // (QuizController::CURRENT_CONSENT_VERSION) : un frontend
            // resté sur un ancien texte de consentement (cache navigateur,
            // déploiement partiel) ne doit jamais faire passer un
            // consentement à un texte que l'utilisateur n'a pas réellement
            // vu.
            'consent_version' => ['required', 'string', 'in:'.QuizController::CURRENT_CONSENT_VERSION],
        ];
    }
}
