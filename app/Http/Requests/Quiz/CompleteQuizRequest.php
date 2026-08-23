<?php

namespace App\Http\Requests\Quiz;

use Illuminate\Foundation\Http\FormRequest;

class CompleteQuizRequest extends FormRequest
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
        ];
    }
}
