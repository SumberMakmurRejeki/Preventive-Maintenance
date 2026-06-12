<?php

namespace App\Http\Requests\Breakdown;

use App\Models\Breakdown;
use Illuminate\Foundation\Http\FormRequest;

class CloseBreakdownRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'root_cause' => trim((string) $this->input('root_cause')),
            'action_taken' => trim((string) $this->input('action_taken')),
            'countermeasure' => trim((string) $this->input('countermeasure')),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        /** @var Breakdown|null $breakdown */
        $breakdown = $this->route('breakdown');

        return [
            'root_cause' => ['required', 'string'],
            'action_taken' => ['required', 'string'],
            'countermeasure' => ['required', 'string'],
            'closed_at' => [
                'required',
                'date',
                'after_or_equal:' . optional($breakdown?->breakdown_at)->format('Y-m-d H:i:s'),
            ],
        ];
    }
}

