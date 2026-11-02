<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'student';
    }

    public function rules(): array
    {
        return ['study_group_id' => ['nullable', 'integer', Rule::exists('study_groups', 'id')->where('registration_open', true)], 'message' => ['nullable', 'string', 'max:1500']];
    }

    public function messages(): array
    {
        return ['study_group_id.exists' => 'Приём заявок в эту группу закрыт.'];
    }
}
