<?php

namespace App\Http\Requests\PM;

use Illuminate\Foundation\Http\FormRequest;

class StorePmExecutionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalizedAction = [];
        foreach ((array) $this->input('execution_action', []) as $standardId => $value) {
            $normalizedAction[$standardId] = strtoupper(trim((string) $value));
        }

        $normalizedNotes = [];
        foreach ((array) $this->input('part_notes', []) as $partId => $value) {
            $normalizedNotes[$partId] = trim((string) $value);
        }

        $this->merge([
            'execution_action' => $normalizedAction,
            'part_notes' => $normalizedNotes,
        ]);
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'execution_action' => ['nullable', 'array'],
            'execution_action.*' => ['nullable', 'string', 'max:50'],
            'execution_number' => ['nullable', 'array'],
            'execution_number.*' => ['nullable', 'numeric'],
            'part_notes' => ['nullable', 'array'],
            'part_notes.*' => ['nullable', 'string'],
            'media_files' => ['nullable', 'array'],
            'media_files.*' => ['nullable', 'array'],
            'media_files.*.*' => ['file'],
        ];
    }
}

