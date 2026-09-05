<?php

namespace Tests\Feature\Feature\PM;

use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class ReconcilePmScheduleDatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_exactly_one_selection_mode(): void
    {
        $schedule = $this->createSchedule();

        $this->artisan('pm:reconcile-schedule-dates')
            ->assertExitCode(1);
        $this->artisan('pm:reconcile-schedule-dates', [
            '--schedule-id' => [$schedule->id],
            '--all' => true,
        ])->assertExitCode(1);
    }

    public function test_dry_run_reports_changes_without_writing_rows(): void
    {
        $schedule = $this->createSchedule();
        $this->createScheduleDate($schedule, '2026-05-01');

        $this->artisan('pm:reconcile-schedule-dates', ['--schedule-id' => [$schedule->id]])
            ->expectsOutputToContain('"created":4')
            ->assertExitCode(0);

        $this->assertSame(1, PmScheduleDate::withTrashed()->count());
        $this->assertScheduleDates($schedule, ['2026-05-01']);
    }

    public function test_it_applies_a_scoped_schedule_reconciliation(): void
    {
        $schedule = $this->createSchedule();

        $this->artisan('pm:reconcile-schedule-dates', [
            '--schedule-id' => [$schedule->id, $schedule->id],
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertScheduleDates($schedule, $this->expectedDates());
    }

    public function test_it_scopes_to_selected_checksheet(): void
    {
        $checksheet = $this->createChecksheet('PM-SELECTED');
        $selectedSchedule = $this->createSchedule($checksheet);
        $otherSchedule = $this->createSchedule($this->createChecksheet('PM-OTHER'));

        $this->artisan('pm:reconcile-schedule-dates', [
            '--checksheet-id' => [$checksheet->id],
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertScheduleDates($selectedSchedule, $this->expectedDates());
        $this->assertScheduleDates($otherSchedule, []);
    }

    public function test_it_reconciles_all_active_schedules(): void
    {
        $first = $this->createSchedule();
        $second = $this->createSchedule($this->createChecksheet('PM-ALL'));

        $this->artisan('pm:reconcile-schedule-dates', ['--all' => true, '--apply' => true])
            ->assertExitCode(0);

        $this->assertScheduleDates($first, $this->expectedDates());
        $this->assertScheduleDates($second, $this->expectedDates());
    }

    public function test_it_excludes_inactive_and_soft_deleted_parents_by_default(): void
    {
        $active = $this->createSchedule();
        $inactiveSchedule = $this->createSchedule($this->createChecksheet('PM-INACTIVE-SCHEDULE'));
        $inactiveSchedule->update(['is_active' => false]);
        $deletedSchedule = $this->createSchedule($this->createChecksheet('PM-DELETED-SCHEDULE'));
        $deletedSchedule->delete();
        $inactiveChecksheet = $this->createChecksheet('PM-INACTIVE', false);
        $inactive = $this->createSchedule($inactiveChecksheet);
        $deletedChecksheet = $this->createChecksheet('PM-DELETED');
        $deleted = $this->createSchedule($deletedChecksheet);
        $deletedChecksheet->delete();

        $this->artisan('pm:reconcile-schedule-dates', ['--all' => true, '--apply' => true])
            ->assertExitCode(0);

        $this->assertScheduleDates($active, $this->expectedDates());
        $this->assertScheduleDates($inactiveSchedule, []);
        $this->assertScheduleDates($deletedSchedule, []);
        $this->assertScheduleDates($inactive, []);
        $this->assertScheduleDates($deleted, []);
    }

    public function test_repeated_apply_is_idempotent(): void
    {
        $schedule = $this->createSchedule();

        $this->artisan('pm:reconcile-schedule-dates', ['--schedule-id' => [$schedule->id], '--apply' => true])
            ->assertExitCode(0);
        $this->artisan('pm:reconcile-schedule-dates', ['--schedule-id' => [$schedule->id], '--apply' => true])
            ->expectsOutputToContain('"unchanged":4')
            ->assertExitCode(0);

        $this->assertScheduleDates($schedule, $this->expectedDates());
        $this->assertSame(4, PmScheduleDate::query()->where('pm_schedule_id', $schedule->id)->count());
    }

    public function test_it_preserves_active_protected_desired_rows_without_conflicts(): void
    {
        $schedule = $this->createSchedule();
        $conflict = $this->createScheduleDate($schedule, '2026-08-07', 'waiting_review');

        $this->artisan('pm:reconcile-schedule-dates', ['--schedule-id' => [$schedule->id], '--apply' => true])
            ->expectsOutputToContain('"unchanged":1')
            ->assertExitCode(0);

        $this->assertSame('waiting_review', $conflict->fresh()->status);
        $this->assertScheduleDates($schedule, $this->expectedDates());
    }

    public function test_it_continues_after_a_schedule_failure(): void
    {
        $failing = $this->createSchedule();
        $succeeding = $this->createSchedule($this->createChecksheet('PM-SUCCEEDING'));
        $event = 'eloquent.creating: '.PmScheduleDate::class;

        Event::listen($event, function (PmScheduleDate $date) use ($failing): void {
            if ($date->pm_schedule_id === $failing->id) {
                throw new RuntimeException('Injected reconciliation failure.');
            }
        });

        try {
            $this->artisan('pm:reconcile-schedule-dates', ['--all' => true, '--apply' => true])
                ->assertExitCode(1);
        } finally {
            Event::forget($event);
        }

        $this->assertScheduleDates($failing, []);
        $this->assertScheduleDates($succeeding, $this->expectedDates());
    }

    public function test_unresolved_schedule_is_reported_and_not_mutated_in_dry_run_and_apply(): void
    {
        $schedule = $this->createSchedule();
        $schedule->update(['operational_from' => null]);
        $existing = $this->createScheduleDate($schedule, '2026-08-07');

        $this->artisan('pm:reconcile-schedule-dates', [
            '--schedule-id' => [$schedule->id],
        ])
            ->expectsOutputToContain('SKIP unresolved schedule_id='.$schedule->id)
            ->expectsOutputToContain('"unresolved":1')
            ->assertExitCode(0);

        $this->artisan('pm:reconcile-schedule-dates', [
            '--schedule-id' => [$schedule->id],
            '--apply' => true,
        ])
            ->expectsOutputToContain('SKIP unresolved schedule_id='.$schedule->id)
            ->expectsOutputToContain('"unresolved":1')
            ->assertExitCode(0);

        $this->assertNotNull(PmScheduleDate::query()->find($existing->id));
        $this->assertSame(1, PmScheduleDate::query()->count());
    }

    /**
     * TASK-003 Slice 3 Defect A — RED regression untuk command path.
     *
     * `pm:reconcile-schedule-dates --apply` harus mencapai kontrak Machine-first
     * lock yang sama dengan reconciler langsung: query lock baris Machine tunggal
     * (`WHERE id = ? ... LIMIT 1`, hasil dari `lockForUpdate()->firstOrFail()`)
     * harus terjadi sebelum mutasi `pm_schedule_dates` apa pun.
     *
     * Query eager-load command (`with('checksheetMachine.machine')`) juga
     * menyentuh tabel `machines`, tetapi bentuknya bulk `WHERE machines.id IN (...)`
     * tanpa `LIMIT 1` sehingga TIDAK dihitung sebagai bukti lock. Deteksi harus
     * spesifik pada bentuk query lock, bukan sembarang query `FROM machines`.
     */
    public function test_apply_command_locks_machine_before_mutating_schedule_dates(): void
    {
        $schedule = $this->createSchedule();
        // Baris stale memaksa mutasi removal; tanggal weekly yang belum ada memaksa creation.
        $this->createScheduleDate($schedule, '2026-05-01');

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->artisan('pm:reconcile-schedule-dates', [
            '--schedule-id' => [$schedule->id],
            '--apply' => true,
        ])->assertExitCode(0);

        $machineLockIndex = null;
        $mutationIndex = null;

        foreach ($queries as $index => $sql) {
            // Query lock tunggal: WHERE "id" = ? (unqualified, bukan "machines"."id" IN (...)) + LIMIT 1.
            if ($machineLockIndex === null
                && preg_match('/from ["`]machines["`] where ["`]id["`] = \?.*limit 1/i', $sql) === 1) {
                $machineLockIndex = $index;
            }

            if ($mutationIndex === null && preg_match('/^(insert into|delete from|update) ["`]pm_schedule_dates["`]/i', $sql) === 1) {
                $mutationIndex = $index;
            }
        }

        $this->assertNotNull($machineLockIndex, 'Expected the command --apply path to reach a single-row Machine lock query (lockForUpdate()->firstOrFail()) before mutating schedule dates.');
        $this->assertNotNull($mutationIndex, 'Expected this scenario to produce a pm_schedule_dates mutation.');
        $this->assertLessThan($mutationIndex, $machineLockIndex, 'Machine lock must occur before any pm_schedule_dates mutation reached through the command path.');
    }

    private function createSchedule(?PmChecksheet $checksheet = null): PmSchedule
    {
        $checksheet ??= $this->createChecksheet('PM-'.(PmChecksheet::query()->count() + 1));
        $location = Location::query()->firstOrCreate([
            'location_code' => 'LOC-COMMAND',
        ], [
            'location_name' => 'Command Line',
            'is_active' => true,
        ]);
        $machine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'MC-'.(Machine::query()->count() + 1),
            'machine_name' => 'Command Machine',
            'qr_token' => 'qr-'.(Machine::query()->count() + 1),
            'is_active' => true,
        ]);
        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $machine->id,
        ]);

        return PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'weekly',
            'weekly_days' => [5],
            'operational_from' => '2026-08-01',
            'start_date' => '2026-08-01',
            'generate_until' => '2026-09-01',
            'is_active' => true,
        ]);
    }

    private function createChecksheet(string $code, bool $active = true): PmChecksheet
    {
        return PmChecksheet::query()->create([
            'checksheet_code' => $code,
            'checksheet_name' => $code,
            'is_active' => $active,
        ]);
    }

    private function createScheduleDate(PmSchedule $schedule, string $date, string $status = 'scheduled'): PmScheduleDate
    {
        return PmScheduleDate::query()->create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $schedule->checksheetMachine->machine_id,
            'scheduled_date' => $date,
            'status' => $status,
            'generated_at' => now(),
        ]);
    }

    private function assertScheduleDates(PmSchedule $schedule, array $expectedDates): void
    {
        $actualDates = PmScheduleDate::query()
            ->where('pm_schedule_id', $schedule->id)
            ->orderBy('scheduled_date')
            ->pluck('scheduled_date')
            ->map(fn ($date): string => $date->toDateString())
            ->all();

        $this->assertSame($expectedDates, $actualDates);
    }

    private function expectedDates(): array
    {
        return ['2026-08-07', '2026-08-14', '2026-08-21', '2026-08-28'];
    }
}
