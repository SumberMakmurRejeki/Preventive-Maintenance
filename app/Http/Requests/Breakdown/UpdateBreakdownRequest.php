<?php

namespace App\Http\Requests\Breakdown;

use App\Models\Breakdown;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBreakdownRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'problem' => trim((string) $this->input('problem')),
            'open_note' => trim((string) $this->input('open_note')),
            'root_cause' => trim((string) $this->input('root_cause')),
            'action_taken' => trim((string) $this->input('action_taken')),
            'countermeasure' => trim((string) $this->input('countermeasure')),
            'change_note' => trim((string) $this->input('change_note')),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        /** @var Breakdown|null $breakdown */
        $breakdown = $this->route('breakdown');
        $breakdownAt = optional($breakdown?->breakdown_at)->format('Y-m-d H:i:s');

        return [
            'problem' => ['required', 'string'],
            'open_note' => ['nullable', 'string'],
            'breakdown_at' => ['required', 'date'],
            'root_cause' => ['nullable', 'string'],
            'action_taken' => ['nullable', 'string'],
            'countermeasure' => ['nullable', 'string'],
            'closed_at' => ['nullable', 'date', 'after_or_equal:breakdown_at'],
            'change_note' => ['required', 'string', 'max:2000'],
            'status' => ['required', 'in:open,closed'],
        ];
    }
}

