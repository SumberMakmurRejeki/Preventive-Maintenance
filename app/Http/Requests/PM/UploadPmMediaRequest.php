<?php

namespace App\Http\Requests\PM;

use Illuminate\Foundation\Http\FormRequest;

class UploadPmMediaRequest extends FormRequest
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
        $this->merge([
            'part_note' => trim((string) $this->input('part_note')),
        ]);
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'part_id' => ['nullable', 'integer'],
            'part_note' => ['nullable', 'string'],
            'media_file' => ['required', 'file'],
        ];
    }
}

