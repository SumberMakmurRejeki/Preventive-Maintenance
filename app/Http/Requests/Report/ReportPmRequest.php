<?php

namespace App\Http\Requests\Report;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportPmRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'machine_id' => ['nullable', 'integer', 'exists:machines,id'],
            'part_name' => ['nullable', 'string', 'max:100'],
            'pic_operator' => ['nullable', 'string', 'max:100'],
            'pic_admin' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['scheduled', 'in_progress', 'waiting_review', 'approved', 'overdue', 'missed'])],
            'warning' => ['nullable', 'string', Rule::in(['warning', 'normal'])],
            'search' => ['nullable', 'string', 'max:120'],
            'export_type' => ['nullable', 'string', Rule::in(['pdf', 'excel'])],
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
            'machine_id' => $this->integer('machine_id') ?: null,
            'part_name' => trim((string) $this->input('part_name', '')) ?: null,
            'pic_operator' => trim((string) $this->input('pic_operator', '')) ?: null,
            'pic_admin' => trim((string) $this->input('pic_admin', '')) ?: null,
            'status' => trim((string) $this->input('status', '')) ?: null,
            'warning' => trim((string) $this->input('warning', '')) ?: null,
            'search' => trim((string) $this->input('search', '')) ?: null,
        ];
    }
}
