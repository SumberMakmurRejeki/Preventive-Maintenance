<?php

namespace App\Http\Requests\PM;

use Illuminate\Foundation\Http\FormRequest;

class ApprovePmExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'review_note' => $this->has('review_note') ? trim((string) $this->input('review_note')) : null,
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'review_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
