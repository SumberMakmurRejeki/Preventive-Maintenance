<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    protected $redirectRoute = 'master-lokasi.index';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'location_code' => strtoupper(trim((string) $this->input('location_code'))),
            'location_name' => trim((string) $this->input('location_name')),
            'description' => trim((string) $this->input('description')),
            'is_active' => filter_var($this->input('is_active', '1'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            'modal_action' => 'create',
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'location_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('locations', 'location_code'),
            ],
            'location_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'modal_action' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location_code.required' => 'Kode lokasi wajib diisi.',
            'location_code.unique' => 'Kode lokasi sudah terdaftar.',
            'location_name.required' => 'Nama lokasi wajib diisi.',
        ];
    }
}
