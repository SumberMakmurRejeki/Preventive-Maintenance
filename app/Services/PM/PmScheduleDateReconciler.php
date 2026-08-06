<?php

namespace App\Services\PM;

use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PmScheduleDateReconciler
{
    private const MUTABLE_STATUSES = ['scheduled', 'overdue', 'missed'];

    /**
     * @return array{examined: int, created: int, restored: int, removed: int, unchanged: int, conflicts: int}
     */
    public function reconcile(PmSchedule $schedule): array
    {
        return DB::transaction(function () use ($schedule): array {
            $lockedSchedule = PmSchedule::query()
                ->active()
                ->whereKey($schedule->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $machineId = (int) $lockedSchedule->checksheetMachine()->firstOrFail()->machine_id;
            $desiredDates = PmScheduleDateGenerator::generate([
                'frequency_type' => $lockedSchedule->frequency_type,
                'weekly_days' => $lockedSchedule->weekly_days,
                'monthly_day' => $lockedSchedule->monthly_day,
                'start_date' => $lockedSchedule->start_date,
                'generate_until' => $lockedSchedule->generate_until,
            ]);
            $missingDates = array_fill_keys($desiredDates, true);
            $dateRows = PmScheduleDate::query()
                ->withTrashed()
                ->where('pm_schedule_id', $lockedSchedule->id)
                ->withCount([
                    'executions as executions_with_trashed_count' => fn (Builder $query) => $query->withTrashed(),
                ])
                ->lockForUpdate()
                ->get();
            $result = [
                'examined' => $dateRows->count(),
                'created' => 0,
                'restored' => 0,
                'removed' => 0,
                'unchanged' => 0,
                'conflicts' => 0,
            ];
            $generatedAt = now();

            foreach ($dateRows as $dateRow) {
                $date = $dateRow->scheduled_date->toDateString();
                $isDesired = isset($missingDates[$date]);
                $isMutable = in_array($dateRow->status, self::MUTABLE_STATUSES, true)
                    && (int) $dateRow->executions_with_trashed_count === 0;

                if (! $isMutable) {
                    if ($isDesired) {
                        $result['conflicts']++;
                        unset($missingDates[$date]);
                    }

                    continue;
                }

                if (! $isDesired) {
                    $dateRow->forceDelete();
                    $result['removed']++;

                    continue;
                }

                unset($missingDates[$date]);

                if ($dateRow->trashed()) {
                    $dateRow->forceFill([
                        'machine_id' => $machineId,
                        'status' => 'scheduled',
                        'status_changed_at' => null,
                        'generated_at' => $generatedAt,
                    ]);
                    $dateRow->restore();
                    $result['restored']++;

                    continue;
                }

                $result['unchanged']++;
            }

            foreach (array_keys($missingDates) as $date) {
                PmScheduleDate::query()->create([
                    'pm_schedule_id' => $lockedSchedule->id,
                    'machine_id' => $machineId,
                    'scheduled_date' => $date,
                    'status' => 'scheduled',
                    'status_changed_at' => null,
                    'generated_at' => $generatedAt,
                ]);
                $result['created']++;
            }

            return $result;
        });
    }
}
