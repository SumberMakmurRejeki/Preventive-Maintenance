<?php

namespace Tests\Feature\Feature\PM;

use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmExecution;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Services\PM\PmScheduleDateReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class PmScheduleDateReconcilerTest extends TestCase
{
    use RefreshDatabase;

    private Machine $machine;

    private PmSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $location = Location::query()->create([
            'location_code' => 'LOC-RECONCILE',
            'location_name' => 'Reconcile Line',
            'is_active' => true,
        ]);

        $this->machine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'MC-RECONCILE',
            'machine_name' => 'Reconcile Machine',
            'qr_token' => 'qr-reconcile',
            'is_active' => true,
        ]);

        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-RECONCILE',
            'checksheet_name' => 'Reconcile Checksheet',
            'is_active' => true,
        ]);

        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);

        $this->schedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'weekly',
            'weekly_days' => [5],
            'start_date' => '2026-08-01',
            'generate_until' => '2026-08-31',
            'is_active' => true,
        ]);
    }

    public function test_it_reconciles_weekly_dates_preserves_history_and_is_idempotent(): void
    {
        $stale = $this->createScheduleDate('2026-05-01', 'overdue');
        $activeExecutionDate = $this->createScheduleDate('2026-05-08');
        $deletedExecutionDate = $this->createScheduleDate('2026-05-15', 'missed');

        $this->createExecution($activeExecutionDate);
        $this->createExecution($deletedExecutionDate)->delete();

        $protectedStatusDates = collect(['in_progress', 'waiting_review', 'approved'])
            ->map(fn (string $status, int $index): PmScheduleDate => $this->createScheduleDate(
                '2026-06-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                $status,
            ));

        $result = $this->reconciler()->reconcile($this->schedule);

        $this->assertSame([
            'examined' => 6,
            'created' => 4,
            'restored' => 0,
            'removed' => 1,
            'unchanged' => 0,
            'conflicts' => 0,
        ], $result);
        $this->assertNull(PmScheduleDate::withTrashed()->find($stale->id));
        $this->assertNotNull(PmScheduleDate::withTrashed()->find($activeExecutionDate->id));
        $this->assertNotNull(PmScheduleDate::withTrashed()->find($deletedExecutionDate->id));

        foreach ($protectedStatusDates as $protectedStatusDate) {
            $this->assertNotNull(PmScheduleDate::withTrashed()->find($protectedStatusDate->id));
        }

        $this->assertSame($this->expectedDates(), $this->visibleAugustDates());

        $beforeSecondRun = $this->dateTimestamps();
        $secondResult = $this->reconciler()->reconcile($this->schedule);

        $this->assertSame([
            'examined' => 9,
            'created' => 0,
            'restored' => 0,
            'removed' => 0,
            'unchanged' => 4,
            'conflicts' => 0,
        ], $secondResult);
        $this->assertSame($beforeSecondRun, $this->dateTimestamps());
    }

    public function test_preview_is_non_mutating_and_matches_reconcile_counts(): void
    {
        $this->createScheduleDate('2026-05-01', 'overdue');
        $desired = $this->createScheduleDate('2026-08-07', 'missed');
        $desired->delete();
        $beforePreview = $this->dateTimestamps();

        $preview = $this->reconciler()->preview($this->schedule);

        $this->assertSame([
            'examined' => 2,
            'created' => 3,
            'restored' => 1,
            'removed' => 1,
            'unchanged' => 0,
            'conflicts' => 0,
        ], $preview);
        $this->assertSame($beforePreview, $this->dateTimestamps());
        $this->assertSame($preview, $this->reconciler()->reconcile($this->schedule));
    }

    public function test_it_restores_a_mutable_soft_deleted_desired_row_without_duplication(): void
    {
        $desired = $this->createScheduleDate('2026-08-07', 'missed', [
            'status_changed_at' => '2026-08-08 09:00:00',
            'generated_at' => '2026-06-11 09:00:00',
        ]);
        $desired->delete();

        $result = $this->reconciler()->reconcile($this->schedule);

        $this->assertSame([
            'examined' => 1,
            'created' => 3,
            'restored' => 1,
            'removed' => 0,
            'unchanged' => 0,
            'conflicts' => 0,
        ], $result);
        $this->assertSame(1, PmScheduleDate::withTrashed()
            ->where('pm_schedule_id', $this->schedule->id)
            ->whereDate('scheduled_date', '2026-08-07')
            ->count());

        $restored = PmScheduleDate::query()->findOrFail($desired->id);
        $this->assertSame('scheduled', $restored->status);
        $this->assertNull($restored->status_changed_at);
        $this->assertNull($restored->deleted_at);
        $this->assertSame($this->expectedDates(), $this->visibleAugustDates());
    }

    public function test_it_preserves_active_protected_desired_dates_as_unchanged(): void
    {
        $statusConflict = $this->createScheduleDate('2026-08-07', 'waiting_review', [
            'status_changed_at' => '2026-08-07 12:00:00',
        ]);
        $executionConflict = $this->createScheduleDate('2026-08-14');
        $deletedExecution = $this->createExecution($executionConflict);
        $deletedExecution->delete();

        $statusConflictBefore = $this->protectedSnapshot($statusConflict);
        $executionConflictBefore = $this->protectedSnapshot($executionConflict);

        $result = $this->reconciler()->reconcile($this->schedule);

        $this->assertSame([
            'examined' => 2,
            'created' => 2,
            'restored' => 0,
            'removed' => 0,
            'unchanged' => 2,
            'conflicts' => 0,
        ], $result);
        $this->assertSame($statusConflictBefore, $this->protectedSnapshot($statusConflict->fresh()));
        $this->assertSame($executionConflictBefore, $this->protectedSnapshot($executionConflict->fresh()));
        $this->assertNotNull(PmExecution::withTrashed()->find($deletedExecution->id));
        $this->assertSame($this->expectedDates(), $this->visibleAugustDates());
    }

    public function test_it_reports_a_soft_deleted_protected_desired_date_as_a_conflict(): void
    {
        $protected = $this->createScheduleDate('2026-08-07', 'waiting_review');
        $protected->delete();

        $result = $this->reconciler()->reconcile($this->schedule);

        $this->assertSame([
            'examined' => 1,
            'created' => 3,
            'restored' => 0,
            'removed' => 0,
            'unchanged' => 0,
            'conflicts' => 1,
        ], $result);
        $this->assertSame('waiting_review', PmScheduleDate::withTrashed()->findOrFail($protected->id)->status);
        $this->assertTrue(PmScheduleDate::withTrashed()->findOrFail($protected->id)->trashed());
        $this->assertSame(['2026-08-14', '2026-08-21', '2026-08-28'], $this->visibleAugustDates());
    }

    public function test_it_rolls_back_stale_removal_when_date_creation_fails(): void
    {
        $stale = $this->createScheduleDate('2026-05-01');
        $event = 'eloquent.creating: '.PmScheduleDate::class;

        Event::listen($event, function (): void {
            throw new RuntimeException('Injected schedule-date creation failure.');
        });

        try {
            $this->reconciler()->reconcile($this->schedule);
            $this->fail('Expected reconciliation to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected schedule-date creation failure.', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $this->assertNotNull(PmScheduleDate::query()->find($stale->id));
        $this->assertSame([], $this->visibleAugustDates());
    }

    private function reconciler(): PmScheduleDateReconciler
    {
        return app(PmScheduleDateReconciler::class);
    }

    private function createScheduleDate(string $date, string $status = 'scheduled', array $attributes = []): PmScheduleDate
    {
        return PmScheduleDate::query()->create(array_merge([
            'pm_schedule_id' => $this->schedule->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => $date,
            'status' => $status,
            'generated_at' => now(),
        ], $attributes));
    }

    private function createExecution(PmScheduleDate $scheduleDate): PmExecution
    {
        return PmExecution::query()->create([
            'pm_schedule_date_id' => $scheduleDate->id,
            'machine_id' => $this->machine->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    private function expectedDates(): array
    {
        return ['2026-08-07', '2026-08-14', '2026-08-21', '2026-08-28'];
    }

    private function visibleAugustDates(): array
    {
        return PmScheduleDate::query()
            ->where('pm_schedule_id', $this->schedule->id)
            ->whereBetween('scheduled_date', ['2026-08-01', '2026-08-31'])
            ->orderBy('scheduled_date')
            ->get()
            ->map(fn (PmScheduleDate $date): string => $date->scheduled_date->toDateString())
            ->all();
    }

    private function dateTimestamps(): array
    {
        return PmScheduleDate::withTrashed()
            ->where('pm_schedule_id', $this->schedule->id)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (PmScheduleDate $date): array => [
                $date->id => [
                    'updated_at' => $date->getRawOriginal('updated_at'),
                    'status_changed_at' => $date->getRawOriginal('status_changed_at'),
                    'deleted_at' => $date->getRawOriginal('deleted_at'),
                ],
            ])
            ->all();
    }

    private function protectedSnapshot(PmScheduleDate $date): array
    {
        return [
            'id' => $date->getRawOriginal('id'),
            'machine_id' => $date->getRawOriginal('machine_id'),
            'scheduled_date' => $date->getRawOriginal('scheduled_date'),
            'status' => $date->getRawOriginal('status'),
            'status_changed_at' => $date->getRawOriginal('status_changed_at'),
            'generated_at' => $date->getRawOriginal('generated_at'),
            'updated_at' => $date->getRawOriginal('updated_at'),
            'deleted_at' => $date->getRawOriginal('deleted_at'),
        ];
    }
}
