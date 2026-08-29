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
}
