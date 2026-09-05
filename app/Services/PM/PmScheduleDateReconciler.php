<?php

namespace App\Services\PM;

use App\Models\Machine;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PmScheduleDateReconciler
{
    // Status occurrence yang boleh direconcile. Sumber tunggal untuk
    // klasifikasi protected/mutable pada reconciliation dan preview.
    public const MUTABLE_STATUSES = ['scheduled', 'overdue', 'missed'];

    /**
     * @return array{examined: int, created: int, restored: int, removed: int, unchanged: int, conflicts: int, unresolved?: bool}
     */
    public function reconcile(PmSchedule $schedule): array
    {
        // Jadwal tanpa awal operasional belum memiliki rentang tanggal yang sah.
        if ($schedule->operational_from === null) {
            return $this->unresolvedResult();
        }

        return DB::transaction(function () use ($schedule): array {
            // TASK-003 Slice 3: Kunci machine terlebih dahulu (urutan ID ascending)
            $machineId = (int) $schedule->checksheetMachine()->firstOrFail()->machine_id;
            Machine::query()
                ->where('id', $machineId)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedSchedule = PmSchedule::query()
                ->active()
                ->whereKey($schedule->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return $this->evaluate($lockedSchedule, true);
        });
    }

    /**
     * @return array{examined: int, created: int, restored: int, removed: int, unchanged: int, conflicts: int, unresolved?: bool}
     */
    public function preview(PmSchedule $schedule): array
    {
        // Preview unresolved tidak boleh membaca atau menghitung tanggal.
        if ($schedule->operational_from === null) {
            return $this->unresolvedResult();
        }

        return $this->evaluate($schedule, false);
    }

    /** @return array{examined: int, created: int, restored: int, removed: int, unchanged: int, conflicts: int, unresolved: bool} */
    private function unresolvedResult(): array
    {
        return [
            'examined' => 0,
            'created' => 0,
            'restored' => 0,
            'removed' => 0,
            'unchanged' => 0,
            'conflicts' => 0,
            'unresolved' => true,
        ];
    }

    /**
     * @return array{examined: int, created: int, restored: int, removed: int, unchanged: int, conflicts: int}
     */
    private function evaluate(PmSchedule $schedule, bool $apply): array
    {
        $machineId = (int) $schedule->checksheetMachine()->firstOrFail()->machine_id;
        $desiredDates = PmScheduleDateGenerator::generate([
            'frequency_type' => $schedule->frequency_type,
            'weekly_days' => $schedule->weekly_days,
            'monthly_day' => $schedule->monthly_day,
            'start_date' => $schedule->start_date,
            'generate_until' => $schedule->generate_until,
        ]);
        $missingDates = array_fill_keys($desiredDates, true);
        $dateRowsQuery = PmScheduleDate::query()
            ->withTrashed()
            ->where('pm_schedule_id', $schedule->id)
            ->orderBy('id', 'asc')
            ->withCount([
                'executions as executions_with_trashed_count' => fn (Builder $query) => $query->withTrashed(),
            ]);
        $dateRows = $apply ? $dateRowsQuery->lockForUpdate()->get() : $dateRowsQuery->get();
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
                    unset($missingDates[$date]);

                    if ($dateRow->trashed()) {
                        $result['conflicts']++;
                    } else {
                        $result['unchanged']++;
                    }
                }

                continue;
            }

            if (! $isDesired) {
                if ($apply) {
                    $dateRow->forceDelete();
                }
                $result['removed']++;

                continue;
            }

            unset($missingDates[$date]);

            if ($dateRow->trashed()) {
                if ($apply) {
                    $dateRow->forceFill([
                        'machine_id' => $machineId,
                        'status' => 'scheduled',
                        'status_changed_at' => null,
                        'generated_at' => $generatedAt,
                    ]);
                    $dateRow->restore();
                }
                $result['restored']++;

                continue;
            }

            $result['unchanged']++;
        }

        foreach (array_keys($missingDates) as $date) {
            if ($apply) {
                PmScheduleDate::query()->create([
                    'pm_schedule_id' => $schedule->id,
                    'machine_id' => $machineId,
                    'scheduled_date' => $date,
                    'status' => 'scheduled',
                    'status_changed_at' => null,
                    'generated_at' => $generatedAt,
                ]);
            }
            $result['created']++;
        }

        return $result;
    }
}
