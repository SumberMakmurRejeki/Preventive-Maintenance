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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        // Kunci tanggal bisnis agar fixture mingguan tetap berada di masa depan.
        Carbon::setTestNow(Carbon::parse('2026-07-15 00:00:00', 'Asia/Jakarta'));

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
            'operational_from' => '2026-08-01',
            'start_date' => '2026-08-01',
            'generate_until' => '2026-09-01',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_past_materialization_preserves_existing_mutable_rows_and_creates_only_from_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 00:00:00', 'Asia/Jakarta'));
        $this->schedule->update([
            'frequency_type' => 'daily',
            'weekly_days' => null,
            'operational_from' => '2026-09-01',
            'start_date' => '2026-09-01',
            'generate_until' => '2026-09-12',
        ]);

        $pastRows = collect([
            $this->createScheduleDate('2026-09-05', 'scheduled'),
            $this->createScheduleDate('2026-09-06', 'overdue'),
            $this->createScheduleDate('2026-09-07', 'missed'),
        ])->mapWithKeys(fn (PmScheduleDate $row): array => [$row->id => [
            'scheduled_date' => $row->scheduled_date->toDateString(),
            'status' => $row->status,
        ]]);

        $this->reconciler()->reconcile($this->schedule->fresh());

        foreach ($pastRows as $id => $expected) {
            $actual = PmScheduleDate::query()->findOrFail($id);
            $this->assertSame($expected['scheduled_date'], $actual->scheduled_date->toDateString());
            $this->assertSame($expected['status'], $actual->status);
        }

        $this->assertSame(
            ['2026-09-05', '2026-09-06', '2026-09-07', '2026-09-10', '2026-09-11', '2026-09-12'],
            PmScheduleDate::query()->orderBy('scheduled_date')->pluck('scheduled_date')->map(fn ($date): string => $date->toDateString())->all(),
        );
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

        // Baris stale sebelum batas materialisasi dipertahankan, bukan dihapus.
        $this->assertSame([
            'examined' => 6,
            'created' => 4,
            'restored' => 0,
            'removed' => 0,
            'unchanged' => 0,
            'conflicts' => 0,
        ], $result);
        $this->assertNotNull(PmScheduleDate::withTrashed()->find($stale->id));
        $this->assertNotNull(PmScheduleDate::withTrashed()->find($activeExecutionDate->id));
        $this->assertNotNull(PmScheduleDate::withTrashed()->find($deletedExecutionDate->id));

        foreach ($protectedStatusDates as $protectedStatusDate) {
            $this->assertNotNull(PmScheduleDate::withTrashed()->find($protectedStatusDate->id));
        }

        $this->assertSame($this->expectedDates(), $this->visibleAugustDates());

        $beforeSecondRun = $this->dateTimestamps();
        $secondResult = $this->reconciler()->reconcile($this->schedule);

        $this->assertSame([
            'examined' => 10,
            'created' => 0,
            'restored' => 0,
            'removed' => 0,
            'unchanged' => 4,
            'conflicts' => 0,
        ], $secondResult);
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
            // Baris stale sebelum batas materialisasi dipertahankan, bukan dihapus.
            'removed' => 0,
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

    public function test_unresolved_schedule_preview_and_reconcile_skip_without_mutation(): void
    {
        $existing = $this->createScheduleDate('2026-08-07');
        $this->schedule->update(['operational_from' => null]);

        $preview = $this->reconciler()->preview($this->schedule->fresh());
        $this->assertSame([
            'examined' => 0,
            'created' => 0,
            'restored' => 0,
            'removed' => 0,
            'unchanged' => 0,
            'conflicts' => 0,
            'unresolved' => true,
        ], $preview);

        $result = $this->reconciler()->reconcile($this->schedule->fresh());

        $this->assertSame($preview, $result);
        $this->assertNotNull(PmScheduleDate::query()->find($existing->id));
        $this->assertSame(1, PmScheduleDate::query()->count());
    }

    /**
     * TASK-003 Slice 3 Defect A — regresi RED untuk lock Machine-first pada reconciler langsung.
     *
     * `reconcile()` wajib mengunci baris Machine induk sebelum menjalankan
     * query mutasi apa pun (`insert/update/delete`) ke `pm_schedule_dates`.
     * Terhadap HEAD sebelum koreksi ini, `reconcile()` tidak pernah merujuk tabel
     * `machines`, sehingga assertion ini gagal karena query Machine tidak ditemukan,
     * bukan karena urutan lock salah.
     */
    public function test_reconcile_locks_machine_before_mutating_schedule_dates(): void
    {
        // Stale row forces a removal mutation; missing weekly dates force creation.
        $this->createScheduleDate('2026-05-01');

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->reconciler()->reconcile($this->schedule);

        [$machineIndex, $mutationIndex] = $this->firstMachineAndScheduleDateMutationIndexes($queries);

        $this->assertNotNull($machineIndex, 'Expected reconcile() to query the machines table before mutating schedule dates.');
        $this->assertNotNull($mutationIndex, 'Expected this scenario to produce a pm_schedule_dates mutation.');
        $this->assertLessThan($mutationIndex, $machineIndex, 'Machine must be locked before any pm_schedule_dates mutation.');
    }

    /**
     * @param  list<string>  $queries
     * @return array{0: int|null, 1: int|null}
     */
    private function firstMachineAndScheduleDateMutationIndexes(array $queries): array
    {
        $machineIndex = null;
        $mutationIndex = null;

        foreach ($queries as $index => $sql) {
            if ($machineIndex === null && preg_match('/from ["`]machines["`]/i', $sql) === 1) {
                $machineIndex = $index;
            }

            if ($mutationIndex === null && preg_match('/^(insert into|delete from|update) ["`]pm_schedule_dates["`]/i', $sql) === 1) {
                $mutationIndex = $index;
            }
        }

        return [$machineIndex, $mutationIndex];
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
        // Normalisasi tanggal kalender agar assertion tidak bergantung pada format driver database.
        return [
            'id' => $date->getRawOriginal('id'),
            'machine_id' => $date->getRawOriginal('machine_id'),
            'scheduled_date' => $date->scheduled_date->toDateString(),
            'status' => $date->getRawOriginal('status'),
            'status_changed_at' => $date->getRawOriginal('status_changed_at'),
            'generated_at' => $date->getRawOriginal('generated_at'),
            'updated_at' => $date->getRawOriginal('updated_at'),
            'deleted_at' => $date->getRawOriginal('deleted_at'),
        ];
    }
}
