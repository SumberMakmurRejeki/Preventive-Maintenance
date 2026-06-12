<?php

namespace App\Http\Requests\PM;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePmReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = [];
        foreach ((array) $this->input('items', []) as $itemId => $itemPayload) {
            if (! is_array($itemPayload)) {
                continue;
            }

            $items[$itemId] = [
                'action_value' => isset($itemPayload['action_value'])
                    ? strtoupper(trim((string) $itemPayload['action_value']))
                    : null,
                'number_value' => $itemPayload['number_value'] ?? null,
            ];
        }

        $partNotes = [];
        foreach ((array) $this->input('part_notes', []) as $partId => $value) {
            $partNotes[$partId] = trim((string) $value);
        }

        $submittedAt = trim((string) $this->input('submitted_at', ''));

        $this->merge([
            'items' => $items,
            'part_notes' => $partNotes,
            'submitted_at' => $submittedAt !== '' ? str_replace('T', ' ', $submittedAt) : null,
            'change_note' => trim((string) $this->input('change_note', '')),
            'review_note' => $this->has('review_note') ? trim((string) $this->input('review_note')) : null,
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.action_value' => ['nullable', 'string', 'max:50'],
            'items.*.number_value' => ['nullable', 'numeric'],
            'part_notes' => ['nullable', 'array'],
            'part_notes.*' => ['nullable', 'string'],
            'submitted_at' => ['required', 'date'],
            'change_note' => ['required', 'string', 'max:1000'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'submitted_at' => 'tanggal submit PM',
        ];
    }
}
