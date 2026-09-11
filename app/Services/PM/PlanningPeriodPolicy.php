<?php

namespace App\Services\PM;

use Carbon\Carbon;

/**
 * Sumber tunggal untuk periode perencanaan jadwal PM (operational window).
 *
 * `initialMonths()` menentukan panjang default jendela (12 bulan kalender).
 * `planningWindow()` menghitung batas bawah dan batas atas perencanaan dari
 * tanggal bisnis hari ini dan tanggal operasional mulai (operational_from).
 * Aritmetika memakai no-overflow sehingga akhir bulan terjaga dengan benar.
 */
class PlanningPeriodPolicy
{
    public function initialMonths(): int
    {
        return 12;
    }

    /**
     * @return array{planning_start: Carbon, planning_end: Carbon}
     */
    public function planningWindow(Carbon $businessToday, Carbon $operationalFrom): array
    {
        $planningStart = $operationalFrom->max($businessToday);

        return [
            'planning_start' => $planningStart,
            'planning_end' => $planningStart->copy()->addMonthsNoOverflow($this->initialMonths()),
        ];
    }

    /**
     * Menentukan tanggal paling awal materialisasi occurrence live.
     * Nilai NULL mempertahankan jalur legacy unresolved tanpa fallback.
     */
    public function materializationStart(?Carbon $operationalFrom, ?Carbon $businessToday = null): ?Carbon
    {
        if ($operationalFrom === null) {
            return null;
        }

        $today = $businessToday?->copy()->startOfDay() ?? BusinessDate::today();

        return $operationalFrom->copy()->startOfDay()->max($today);
    }

    /**
     * Batas awal generasi tanggal desired: start_date eksplisit atau batas
     * materialisasi, mana yang lebih baru. Memastikan start_date yang lebih
     * baru dari operational_from tetap dihormati tanpa materialisasi backlog.
     */
    public function generationStart(?Carbon $startDate, ?Carbon $materializationStart): ?Carbon
    {
        if ($startDate === null) {
            return $materializationStart?->copy();
        }

        if ($materializationStart === null) {
            return $startDate->copy();
        }

        return $startDate->copy()->startOfDay()->max($materializationStart);
    }
}
