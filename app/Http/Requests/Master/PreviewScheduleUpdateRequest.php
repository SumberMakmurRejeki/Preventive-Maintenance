<?php

namespace App\Http\Requests\Master;

use App\Models\PmSchedule;
use App\Services\PM\BusinessDate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Throwable;

class PreviewScheduleUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $payload = $this->input('wizard_payload');

        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        $this->merge([
            'wizard_payload' => is_array($payload) ? $payload : null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wizard_payload' => ['required', 'array'],
            'wizard_payload.schedule' => ['required', 'array'],
            'wizard_payload.schedule.frequency_type' => ['required', 'in:daily,weekly,monthly'],
            'wizard_payload.schedule.operational_from' => ['nullable', 'date'],
            'wizard_payload.schedule.start_date' => ['nullable', 'date'],
            'wizard_payload.schedule.generate_until' => ['nullable', 'date'],
            'wizard_payload.schedule.weekly_days' => ['nullable', 'array'],
            'wizard_payload.schedule.weekly_days.*' => ['integer', 'between:0,6', 'distinct:strict'],
            'wizard_payload.schedule.monthly_day' => ['nullable', 'integer', 'between:1,31'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $schedule = $this->input('wizard_payload.schedule', []);
                $submitted = $schedule['operational_from'] ?? null;

                // NULL legacy tidak ditolak dan diteruskan ke service sebagai unresolved.
                if (empty($submitted)) {
                    return;
                }

                try {
                    $submittedDate = Carbon::parse($submitted)->startOfDay();
                } catch (Throwable) {
                    return;
                }

                $businessToday = BusinessDate::today();

                $storedSchedule = PmSchedule::query()
                    ->whereHas(
                        'checksheetMachine',
                        fn (Builder $query) => $query->where('pm_checksheet_id', $this->route('id'))
                    )
                    ->orderBy('id')
                    ->first();
                $storedFrom = $storedSchedule?->operational_from;

                if (! $submittedDate->lt($businessToday)) {
                    return;
                }

                $isUnchanged = $storedFrom !== null
                    && $storedFrom->toDateString() === $submittedDate->toDateString();

                if (! $isUnchanged) {
                    $validator->errors()->add(
                        'wizard_payload.schedule.operational_from',
                        'Tanggal mulai jadwal PRIME tidak boleh diubah ke masa lalu sebelum tanggal bisnis hari ini.',
                    );
                }
            },
        ];
    }
}
