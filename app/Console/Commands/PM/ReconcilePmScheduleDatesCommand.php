<?php

namespace App\Console\Commands\PM;

use App\Models\PmSchedule;
use App\Services\PM\PmScheduleDateReconciler;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class ReconcilePmScheduleDatesCommand extends Command
{
    protected $signature = 'pm:reconcile-schedule-dates
        {--schedule-id=* : Reconcile specific PM schedule IDs}
        {--checksheet-id=* : Reconcile schedules for specific PM checksheet IDs}
        {--all : Reconcile all active PM schedules with active checksheets}
        {--apply : Persist changes instead of performing a dry run}';

    protected $description = 'Preview or reconcile PM schedule dates for a scoped active selection';

    public function __construct(
        private readonly PmScheduleDateReconciler $reconciler,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $scheduleIds = $this->optionIds('schedule-id');
        $checksheetIds = $this->optionIds('checksheet-id');

        if ($scheduleIds === null || $checksheetIds === null) {
            $this->error('Selection IDs must be positive integers.');

            return self::FAILURE;
        }

        $selectionModes = ($scheduleIds !== [] ? 1 : 0)
            + ($checksheetIds !== [] ? 1 : 0)
            + ($this->option('all') ? 1 : 0);

        if ($selectionModes !== 1) {
            $this->error('Choose exactly one selection mode: --schedule-id, --checksheet-id, or --all.');

            return self::FAILURE;
        }

        $schedules = $this->schedules($scheduleIds, $checksheetIds)->get();
        $apply = (bool) $this->option('apply');
        $hasProblem = false;

        foreach ($schedules as $schedule) {
            try {
                $result = $apply
                    ? $this->reconciler->reconcile($schedule)
                    : $this->reconciler->preview($schedule);
                $record = [
                    'mode' => $apply ? 'apply' : 'dry-run',
                    'schedule_id' => $schedule->id,
                    'checksheet_id' => $schedule->checksheetMachine->checksheet->id,
                    'machine_id' => $schedule->checksheetMachine->machine->id,
                    ...$result,
                ];

                $this->line((string) json_encode($record, JSON_THROW_ON_ERROR));
                $hasProblem = $hasProblem || $result['conflicts'] > 0;
            } catch (Throwable $exception) {
                $this->error((string) json_encode([
                    'mode' => $apply ? 'apply' : 'dry-run',
                    'schedule_id' => $schedule->id,
                    'checksheet_id' => $schedule->checksheetMachine->checksheet->id,
                    'machine_id' => $schedule->checksheetMachine->machine->id,
                    'failure' => $exception->getMessage(),
                ], JSON_THROW_ON_ERROR));
                $hasProblem = true;
            }
        }

        $this->line((string) json_encode([
            'mode' => $apply ? 'apply' : 'dry-run',
            'selected' => $schedules->count(),
            'status' => $hasProblem ? 'failed' : 'ok',
        ], JSON_THROW_ON_ERROR));

        return $hasProblem ? self::FAILURE : self::SUCCESS;
    }

    /** @return Builder<PmSchedule> */
    private function schedules(array $scheduleIds, array $checksheetIds): Builder
    {
        return PmSchedule::query()
            ->active()
            ->with(['checksheetMachine.checksheet', 'checksheetMachine.machine'])
            ->whereHas('checksheetMachine.checksheet', function (Builder $query) use ($checksheetIds): void {
                $query->active();

                if ($checksheetIds !== []) {
                    $query->whereIn('pm_checksheets.id', $checksheetIds);
                }
            })
            ->when($scheduleIds !== [], fn (Builder $query) => $query->whereKey($scheduleIds))
            ->orderBy('id');
    }

    /** @return list<int>|null */
    private function optionIds(string $name): ?array
    {
        $ids = [];

        foreach ((array) $this->option($name) as $value) {
            if (! is_string($value) && ! is_int($value)) {
                return null;
            }

            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($id === false) {
                return null;
            }

            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }
}
