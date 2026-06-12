<?php

namespace App\Http\Requests\Report;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportBreakdownRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'status' => ['nullable', 'string', Rule::in(['OPEN', 'CLOSED'])],
            'search' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'Tanggal akhir tidak boleh lebih awal dari tanggal mulai.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedFilters(): array
    {
        return [
            'start_date' => trim((string) $this->input('start_date', '')) ?: null,
            'end_date' => trim((string) $this->input('end_date', '')) ?: null,
            'location_id' => $this->integer('location_id') ?: null,
            'status' => trim((string) $this->input('status', '')) ?: null,
            'search' => trim((string) $this->input('search', '')) ?: null,
        ];
    }
}
