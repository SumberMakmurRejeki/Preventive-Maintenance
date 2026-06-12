<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CalendarEventsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'event_type' => ['nullable', Rule::in(['all', 'pm', 'breakdown'])],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'machine_id' => ['nullable', 'integer', 'exists:machines,id'],
            'status' => ['nullable', Rule::in([
                'scheduled',
                'in_progress',
                'waiting_review',
                'approved',
                'overdue',
                'missed',
                'open',
                'closed',
            ])],
        ];
    }

    public function messages(): array
    {
        return [
            'start.date' => 'Parameter start harus berupa tanggal yang valid.',
            'end.date' => 'Parameter end harus berupa tanggal yang valid.',
            'end.after_or_equal' => 'Parameter end tidak boleh lebih kecil dari start.',
        ];
    }
}
