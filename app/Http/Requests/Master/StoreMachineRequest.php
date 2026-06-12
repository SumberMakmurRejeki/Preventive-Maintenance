<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMachineRequest extends FormRequest
{
    protected $redirectRoute = 'master-mesin.index';

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
            'machine_code' => strtoupper(trim((string) $this->input('machine_code'))),
            'machine_name' => trim((string) $this->input('machine_name')),
            'description' => trim((string) $this->input('description')),
            'is_active' => filter_var($this->input('is_active', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
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
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
            ],
            'machine_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('machines', 'machine_code'),
            ],
            'machine_name' => ['required', 'string', 'max:100'],
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
            'location_id.required' => 'Lokasi wajib dipilih.',
            'location_id.exists' => 'Lokasi harus aktif dan tersedia.',
            'machine_code.required' => 'Kode mesin wajib diisi.',
            'machine_code.unique' => 'Kode mesin sudah dipakai.',
            'machine_name.required' => 'Nama mesin wajib diisi.',
        ];
    }
}
