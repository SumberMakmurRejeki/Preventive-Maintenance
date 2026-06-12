<?php

namespace App\Http\Requests\Breakdown;

use Illuminate\Foundation\Http\FormRequest;

class StoreBreakdownRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'part_selection' => trim((string) $this->input('part_selection')),
            'custom_part_name' => trim((string) $this->input('custom_part_name')),
            'problem' => trim((string) $this->input('problem')),
            'open_note' => trim((string) $this->input('open_note')),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'part_selection' => ['required', 'string'],
            'custom_part_name' => ['nullable', 'string', 'max:100', 'required_if:part_selection,other'],
            'problem' => ['required', 'string'],
            'breakdown_at' => ['required', 'date'],
            'open_note' => ['nullable', 'string'],
            'media' => ['nullable', 'array'],
            'media.*' => [
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm',
                'max:51200',
            ],
            'temp_media_ids' => ['nullable', 'array'],
            'temp_media_ids.*' => ['string'],
        ];
    }
}
