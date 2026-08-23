<?php

namespace App\Http\Requests\Quiz;

use Illuminate\Foundation\Http\FormRequest;

class CompareRequest extends FormRequest
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
            'session_token' => ['nullable', 'uuid'],
            'theme_id' => ['nullable', 'integer', 'exists:themes,id'],
            'axe_ideologique' => ['nullable', 'in:economique,societal,aucun'],
        ];
    }
}
