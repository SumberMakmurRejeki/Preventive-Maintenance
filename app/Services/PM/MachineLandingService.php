<?php

namespace App\Services\PM;

use App\Models\Breakdown;
use App\Models\Machine;
use App\Models\PmChecksheetPart;
use App\Models\PmExecution;
use App\Models\PmScheduleDate;
use Illuminate\Support\Collection;

class MachineLandingService
{
    /**
     * @return array{
     *   lastPm: ?PmExecution,
     *   nextPm: ?PmScheduleDate,
     *   hasActivePmSchedule: bool,
     *   openBreakdowns: Collection<int, Breakdown>,
     *   partOptions: Collection<int, string>
     * }
     */
    public function build(Machine $machine): array
    {
        $lastPm = PmExecution::query()
            ->where('machine_id', $machine->id)
            ->whereIn('status', ['waiting_review', 'approved'])
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->first();

        $nextPm = PmScheduleDate::query()
            ->where('machine_id', $machine->id)
            ->whereIn('status', ['scheduled', 'overdue'])
            ->orderBy('scheduled_date')
            ->first();

        $hasActivePmSchedule = PmScheduleDate::query()
            ->where('machine_id', $machine->id)
            ->whereIn('status', ['scheduled', 'overdue'])
            ->exists();

        $openBreakdowns = Breakdown::query()
            ->where('machine_id', $machine->id)
            ->where('status', 'open')
            ->latest('breakdown_at')
            ->get();

        $partOptions = PmChecksheetPart::query()
            ->select('pm_checksheet_parts.part_name')
            ->join('pm_checksheet_machines', 'pm_checksheet_machines.id', '=', 'pm_checksheet_parts.pm_checksheet_machine_id')
            ->where('pm_checksheet_machines.machine_id', $machine->id)
            ->where('pm_checksheet_parts.is_active', true)
            ->orderBy('pm_checksheet_parts.part_name')
            ->pluck('pm_checksheet_parts.part_name')
            ->unique()
            ->values();

        if (! $partOptions->contains('Lainnya')) {
            $partOptions->push('Lainnya');
        }

        return [
            'lastPm' => $lastPm,
            'nextPm' => $nextPm,
            'hasActivePmSchedule' => $hasActivePmSchedule,
            'openBreakdowns' => $openBreakdowns,
            'partOptions' => $partOptions,
        ];
    }
}
