<?php

namespace App\Http\Requests\Master;

use App\Models\Machine;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMachineRequest extends FormRequest
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
        $machine = Machine::query()->find($this->route('machineId'));

        $this->merge([
            'machine_id' => $machine?->id,
            'machine_code' => strtoupper(trim((string) $this->input('machine_code'))),
            'machine_name' => trim((string) $this->input('machine_name')),
            'description' => trim((string) $this->input('description')),
            'modal_action' => 'edit',
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        $machine = Machine::query()->findOrFail($this->route('machineId'));

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
                Rule::in([$machine->machine_code]),
            ],
            'machine_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'machine_id' => ['nullable', 'integer'],
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
            'machine_code.in' => 'Kode mesin tidak dapat diubah setelah dibuat.',
            'machine_name.required' => 'Nama mesin wajib diisi.',
        ];
    }
}
