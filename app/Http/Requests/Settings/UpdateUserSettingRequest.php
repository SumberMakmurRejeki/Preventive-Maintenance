<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserSettingRequest extends FormRequest
{
    protected $redirectRoute = 'settings-users.index';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $userId = (int) $this->route('userId');

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'username' => strtolower(trim((string) $this->input('username'))),
            'role' => strtolower(trim((string) $this->input('role'))),
            'is_active' => filter_var($this->input('is_active', '1'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            'modal_action' => 'edit',
            'user_id' => $userId,
        ]);
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>|string>
     */
    public function rules(): array
    {
        $userId = (int) $this->route('userId');

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($userId)],
            'role' => ['required', Rule::in(['admin', 'operator'])],
            'is_active' => ['required', 'boolean'],
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
            'name.required' => 'Nama wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah digunakan.',
            'role.required' => 'Role wajib dipilih.',
            'role.in' => 'Role wajib Admin atau Operator.',
            'is_active.required' => 'Status wajib dipilih.',
        ];
    }
}
