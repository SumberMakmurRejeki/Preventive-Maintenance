<?php

namespace Tests\Feature\Feature\Master;

use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\Master\PmChecksheetService;
use App\Services\PM\PmScheduleDateReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class PmChecksheetScheduleReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::query()->create([
            'name' => 'Admin PRIME',
            'username' => 'admin.schedule',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->operator = User::query()->create([
            'name' => 'Operator PRIME',
            'username' => 'operator.schedule',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'is_active' => true,
        ]);
        $this->location = Location::query()->create([
            'location_code' => 'LOC-SCHEDULE',
            'location_name' => 'Schedule Line',
            'is_active' => true,
        ]);
    }

    public function test_preview_is_scoped_and_does_not_write_schedule_dates(): void
    {
        $checksheet = $this->createChecksheet();
        $schedule = $this->createSchedule($checksheet, 'MC-PREVIEW');
        $otherSchedule = $this->createSchedule($this->createChecksheet('PM-OTHER'), 'MC-OTHER');
        $this->createScheduleDate($schedule, '2026-05-01');

        $preview = app(PmChecksheetService::class)->previewScheduleDates($checksheet);

        $this->assertSame(1, $preview['selected']);
        $this->assertSame(4, $preview['totals']['created']);
        $this->assertSame(1, $preview['totals']['removed']);
        $this->assertSame(0, $preview['totals']['conflicts']);
        $this->assertSame(1, PmScheduleDate::withTrashed()->where('pm_schedule_id', $schedule->id)->count());
        $this->assertSame(0, PmScheduleDate::withTrashed()->where('pm_schedule_id', $otherSchedule->id)->count());
    }

    public function test_admin_sees_ready_preview_with_machine_identity_and_apply_form(): void
    {
        $checksheet = $this->createChecksheet();
        $this->createSchedule($checksheet, 'MC-READY');

        $this->actingAs($this->admin)
            ->get(route('master-checksheet.show', $checksheet->id))
            ->assertOk()
            ->assertSee('Sinkronisasi Jadwal PM')
            ->assertSee('MC-READY')
            ->assertSee('Terapkan Sinkronisasi');
    }

    public function test_admin_can_apply_all_checksheet_schedule_dates_and_writes_one_activity_log(): void
    {
        $checksheet = $this->createChecksheet();
        $first = $this->createSchedule($checksheet, 'MC-FIRST');
        $second = $this->createSchedule($checksheet, 'MC-SECOND');

        $this->actingAs($this->admin)
            ->post(route('master-checksheet.reconcile-schedule-dates', $checksheet->id), ['confirmed' => 1])
            ->assertRedirect(route('master-checksheet.show', $checksheet->id))
            ->assertSessionHas('flash_success');

        $this->assertScheduleDates($first, $this->expectedDates());
        $this->assertScheduleDates($second, $this->expectedDates());
        $this->assertSame(1, UserActivityLog::query()
            ->where('module_name', 'master_checksheet')
            ->where('action', 'reconcile_schedule_dates')
            ->where('record_id', $checksheet->id)
            ->count());
    }

    public function test_conflicts_render_a_warning_and_block_apply_without_writes(): void
    {
        $checksheet = $this->createChecksheet();
        $schedule = $this->createSchedule($checksheet, 'MC-CONFLICT');
        $conflict = $this->createScheduleDate($schedule, '2026-08-07', 'waiting_review');
        $conflict->delete();

        $this->actingAs($this->admin)
            ->get(route('master-checksheet.show', $checksheet->id))
            ->assertOk()
            ->assertSee('Konflik jadwal ditemukan')
            ->assertDontSee('Terapkan Sinkronisasi');
        $this->actingAs($this->admin)
            ->post(route('master-checksheet.reconcile-schedule-dates', $checksheet->id), ['confirmed' => 1])
            ->assertRedirect(route('master-checksheet.show', $checksheet->id))
            ->assertSessionHas('flash_error');

        $this->assertScheduleDates($schedule, []);
        $this->assertTrue(PmScheduleDate::withTrashed()->findOrFail($conflict->id)->trashed());
    }

    public function test_inactive_and_empty_checksheet_states_cannot_be_applied(): void
    {
        $inactive = $this->createChecksheet('PM-INACTIVE', false);
        $this->createSchedule($inactive, 'MC-INACTIVE');
        $empty = $this->createChecksheet('PM-EMPTY');

        $this->actingAs($this->admin)
            ->get(route('master-checksheet.show', $inactive->id))
            ->assertOk()
            ->assertSee('Checksheet nonaktif tidak dapat disinkronkan.');
        $this->actingAs($this->admin)
            ->post(route('master-checksheet.reconcile-schedule-dates', $inactive->id), ['confirmed' => 1])
            ->assertRedirect(route('master-checksheet.show', $inactive->id))
            ->assertSessionHas('flash_error');
        $this->actingAs($this->admin)
            ->get(route('master-checksheet.show', $empty->id))
            ->assertOk()
            ->assertSee('Tidak ada jadwal PM aktif untuk disinkronkan.');
        $this->actingAs($this->admin)
            ->post(route('master-checksheet.reconcile-schedule-dates', $empty->id), ['confirmed' => 1])
            ->assertRedirect(route('master-checksheet.show', $empty->id))
            ->assertSessionHas('flash_error');
    }

    public function test_no_actionable_changes_are_rejected_without_an_activity_log(): void
    {
        $checksheet = $this->createChecksheet();
        $schedule = $this->createSchedule($checksheet, 'MC-NOOP');
        app(PmScheduleDateReconciler::class)->reconcile($schedule);

        $this->actingAs($this->admin)
            ->get(route('master-checksheet.show', $checksheet->id))
            ->assertOk()
            ->assertSee('Tidak ada perubahan jadwal yang dapat diterapkan.')
            ->assertDontSee('Terapkan Sinkronisasi');
        $this->actingAs($this->admin)
            ->post(route('master-checksheet.reconcile-schedule-dates', $checksheet->id), ['confirmed' => 1])
            ->assertRedirect(route('master-checksheet.show', $checksheet->id))
            ->assertSessionHas('flash_error');

        $this->assertSame(0, UserActivityLog::query()
            ->where('action', 'reconcile_schedule_dates')
            ->count());
    }

    public function test_apply_is_scoped_to_the_requested_checksheet(): void
    {
        $checksheet = $this->createChecksheet();
        $selected = $this->createSchedule($checksheet, 'MC-SELECTED');
        $other = $this->createSchedule($this->createChecksheet('PM-OTHER'), 'MC-UNSELECTED');

        $this->actingAs($this->admin)
            ->post(route('master-checksheet.reconcile-schedule-dates', $checksheet->id), ['confirmed' => 1])
            ->assertRedirect();

        $this->assertScheduleDates($selected, $this->expectedDates());
        $this->assertScheduleDates($other, []);
    }

    public function test_apply_rolls_back_every_schedule_when_one_reconciliation_fails(): void
    {
        $checksheet = $this->createChecksheet();
        $first = $this->createSchedule($checksheet, 'MC-ROLLBACK-1');
        $second = $this->createSchedule($checksheet, 'MC-ROLLBACK-2');
        $event = 'eloquent.creating: '.PmScheduleDate::class;
        Event::listen($event, function (PmScheduleDate $date) use ($second): void {
            if ($date->pm_schedule_id === $second->id) {
                throw new RuntimeException('Injected checksheet reconciliation failure.');
            }
        });

        try {
            $this->actingAs($this->admin)
                ->post(route('master-checksheet.reconcile-schedule-dates', $checksheet->id), ['confirmed' => 1])
                ->assertRedirect(route('master-checksheet.show', $checksheet->id))
                ->assertSessionHas('flash_error', 'Terjadi kesalahan saat menerapkan sinkronisasi jadwal PM. Silakan coba lagi.');
        } finally {
            Event::forget($event);
        }

        $this->assertScheduleDates($first, []);
        $this->assertScheduleDates($second, []);
    }

    public function test_apply_requires_confirmation_without_mutating_schedule_dates(): void
    {
        $checksheet = $this->createChecksheet();
        $schedule = $this->createSchedule($checksheet, 'MC-CONFIRM');

        $this->actingAs($this->admin)
            ->from(route('master-checksheet.show', $checksheet->id))
            ->post(route('master-checksheet.reconcile-schedule-dates', $checksheet->id))
            ->assertRedirect(route('master-checksheet.show', $checksheet->id))
            ->assertSessionHasErrors('confirmed');

        $this->assertScheduleDates($schedule, []);
    }

    public function test_only_admin_can_apply_schedule_reconciliation(): void
    {
        $checksheet = $this->createChecksheet();
        $schedule = $this->createSchedule($checksheet, 'MC-AUTH');

        $this->actingAs($this->operator)
            ->post(route('master-checksheet.reconcile-schedule-dates', $checksheet->id))
            ->assertRedirect('/403');

        $this->assertScheduleDates($schedule, []);
    }

    private function createChecksheet(string $code = 'PM-SCHEDULE', bool $active = true): PmChecksheet
    {
        return PmChecksheet::query()->create([
            'checksheet_code' => $code,
            'checksheet_name' => $code,
            'is_active' => $active,
        ]);
    }

    private function createSchedule(PmChecksheet $checksheet, string $machineCode): PmSchedule
    {
        $machine = Machine::query()->create([
            'location_id' => $this->location->id,
            'machine_code' => $machineCode,
            'machine_name' => $machineCode,
            'qr_token' => strtolower($machineCode),
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
            'start_date' => '2026-08-01',
            'generate_until' => '2026-08-31',
            'is_active' => true,
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
        $this->assertSame($expectedDates, PmScheduleDate::query()
            ->where('pm_schedule_id', $schedule->id)
            ->orderBy('scheduled_date')
            ->pluck('scheduled_date')
            ->map(fn ($date): string => $date->toDateString())
            ->all());
    }

    private function expectedDates(): array
    {
        return ['2026-08-07', '2026-08-14', '2026-08-21', '2026-08-28'];
    }
}
