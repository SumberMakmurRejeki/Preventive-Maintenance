<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Memvalidasi alasan nyata pengguna untuk setiap action lifecycle Machine.
 */
class MachineLifecycleReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Normalisasi reason memastikan whitespace saja tetap gagal validasi required.
        $this->merge([
            'reason' => trim((string) $this->input('reason')),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan perubahan status mesin wajib diisi.',
        ];
    }
}
