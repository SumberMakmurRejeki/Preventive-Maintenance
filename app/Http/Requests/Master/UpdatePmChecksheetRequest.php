<?php

namespace App\Http\Requests\Master;

use App\Models\PmSchedule;
use App\Services\PM\BusinessDate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Throwable;

class UpdatePmChecksheetRequest extends StorePmChecksheetRequest
{
    protected $redirectRoute = 'master-checksheet.edit';

    public function rules(): array
    {
        $checksheetId = (int) $this->route('id');
        $rules = parent::rules();

        $rules['checksheet_code'] = [
            'required',
            'string',
            'max:50',
            Rule::unique('pm_checksheets', 'checksheet_code')->ignore($checksheetId),
        ];

        // Slice 1: pada update, operational_from bersifat opsional agar jalur
        // legacy (NULL / data lama) tetap valid.
        // Validasi tanggal bisnis penuh untuk update dituntaskan di Slice 2
        // (lihat after()).
        $rules['schedule.operational_from'] = ['nullable', 'date'];

        return $rules;
    }

    public function after(): array
    {
        // Lampirkan validasi bisnis tanggal mulai pada closure after() milik
        // Store (wizard) — jangan menggantinya.
        return [
            ...parent::after(),
            function ($validator): void {
                $schedule = $this->input('schedule', []);
                $submitted = $schedule['operational_from'] ?? null;

                // NULL / kosong -> jalur legacy, pertahankan nilai tersimpan.
                // (Kontrak TASK-002: NULL tidak menciptakan backlog.)
                if (empty($submitted)) {
                    return;
                }

                try {
                    $submittedDate = Carbon::parse($submitted)->startOfDay();
                } catch (Throwable) {
                    // Format tidak valid ditangani oleh rule 'date' saja.
                    return;
                }

                $businessToday = BusinessDate::today();

                // Nilai operational_from tersimpan: jadwal aktif pertama checksheet.
                $storedSchedule = PmSchedule::query()
                    ->whereHas(
                        'checksheetMachine',
                        fn (Builder $query) => $query->where('pm_checksheet_id', $this->route('id'))
                    )
                    ->orderBy('id')
                    ->first();
                $storedFrom = $storedSchedule?->operational_from;

                // Nilai pada/ setelah business_today selalu diterima.
                if (! $submittedDate->lt($businessToday)) {
                    return;
                }

                // Nilai di masa lalu hanya diterima jika TIDAK diubah dari
                // nilai tersimpan (kontrak TASK-002: past existing tetap
                // valid jika tidak diubah; mengubah ke masa lalu ditolak).
                $isUnchanged = $storedFrom !== null
                    && $storedFrom->toDateString() === $submittedDate->toDateString();

                if (! $isUnchanged) {
                    $validator->errors()->add(
                        'schedule.operational_from',
                        'Tanggal mulai jadwal PRIME tidak boleh diubah ke masa lalu sebelum tanggal bisnis hari ini.',
                    );
                }
            },
        ];
    }

    /**
     * Redirect kembali ke halaman edit dengan parameter id saat validasi gagal.
     */
    protected function getRedirectUrl()
    {
        if ($this->redirectRoute) {
            return $this->redirector->getUrlGenerator()
                ->route($this->redirectRoute, ['id' => $this->route('id')]);
        }

        return parent::getRedirectUrl();
    }
}
