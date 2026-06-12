<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ResetUserSettingPasswordRequest extends FormRequest
{
    protected $redirectRoute = 'settings-users.index';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'new_password' => (string) $this->input('new_password'),
            'new_password_confirmation' => (string) $this->input('new_password_confirmation'),
            'modal_action' => 'reset_password',
            'user_id' => (int) $this->route('userId'),
        ]);
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'new_password' => ['required', 'string', 'confirmed'],
            'modal_action' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_password.required' => 'Password baru wajib diisi.',
            'new_password.confirmed' => 'Konfirmasi password tidak sesuai.',
        ];
    }
}
