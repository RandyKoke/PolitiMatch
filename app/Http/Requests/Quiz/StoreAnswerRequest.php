<?php

namespace App\Http\Requests\Quiz;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnswerRequest extends FormRequest
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
            'quiz_result_uuid' => ['required', 'uuid'],
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'user_score' => ['required', 'integer', 'in:-2,-1,0,1,2'],
            'was_skipped' => ['boolean'],
            // Requis uniquement pour un quiz anonyme — vérifié par
            // QuizAccessService, pas ici (dépend de l'état en base).
            'session_token' => ['nullable', 'uuid'],
        ];
    }
}
