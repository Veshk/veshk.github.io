<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->email))]);
    }

    public function rules(): array
    {
        return ['last_name' => ['required', 'string', 'max:80'], 'first_name' => ['required', 'string', 'max:80'], 'patronymic' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::min(8)],
            'study_group_id' => ['nullable', 'integer', Rule::exists('study_groups', 'id')->where('registration_open', true)],
            'message' => ['nullable', 'string', 'max:1500']];
    }

    public function messages(): array
    {
        return ['email.unique' => 'Эта почта уже зарегистрирована. Войдите в свой аккаунт, чтобы подать заявку на новую группу.', 'study_group_id.exists' => 'Приём заявок в эту группу закрыт.', 'password.confirmed' => 'Пароли не совпадают.', 'required' => 'Заполните это поле.', 'email.email' => 'Укажите корректную почту.', 'password.min' => 'Используйте не менее 8 символов.'];
    }
}
