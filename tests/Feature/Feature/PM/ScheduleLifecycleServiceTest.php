<?php

namespace Tests\Feature\Feature\PM;

use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmChecksheetPart;
use App\Models\PmChecksheetStandard;
use App\Models\PmExecution;
use App\Models\PmExecutionHistory;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Models\User;
use App\Services\Auth\ActivityLogService;
use App\Services\PM\InvalidScheduleTransitionException;
use App\Services\PM\PmExecutionService;
use App\Services\PM\PmReviewService;
use App\Services\PM\ScheduleLifecycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Menguji ScheduleLifecycleService sebagai satu-satunya seam mutasi lifecycle Schedule.
 *
 * Test ini hanya memakai database test terisolasi (RefreshDatabase).
 */
class ScheduleLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        // ActivityLogService leest de sessie uit de actieve request; bind een
        // request met session zodat audittests dezelfde echte seam gebruiken.
        $request = Request::create('/pm/test', 'POST');
        $request->setLaravelSession(app('session.store'));
        $this->app->instance('request', $request);

        $location = Location::query()->create([
            'location_code' => 'LOC-SLC',
            'location_name' => 'Schedule Lifecycle Location',
            'is_active' => true,
        ]);

        $this->machine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'MC-SLC',
            'machine_name' => 'Schedule Lifecycle Machine',
            'qr_token' => 'qr-slc',
            'is_active' => true,
            'lifecycle_status' => 'active',
        ]);

        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-SLC',
            'checksheet_name' => 'Schedule Lifecycle Checksheet',
            'is_active' => true,
        ]);

        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);

        $this->schedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'daily',
            'operational_from' => '2026-09-01',
            'start_date' => '2026-09-01',
            'generate_until' => '2026-10-01',
            'is_active' => true,
            'lifecycle_status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): ScheduleLifecycleService
    {
        return app(ScheduleLifecycleService::class);
    }

    /**
     * Reason blank wajib ditolak sebelum transaksi dan mutasi.
     */
    public function test_blank_reason_is_rejected_fail_closed(): void
    {
        $this->assertThrows(
            fn () => $this->service()->transition($this->schedule, 'paused', " \t\n"),
            InvalidScheduleTransitionException::class,
        );
        $this->assertSame('active', $this->schedule->fresh()->lifecycle_status);
        $this->assertSame(0, DB::table('user_activity_logs')->where('record_id', $this->schedule->id)->count());
    }

    /**
     * Kegagalan ActivityLogService harus mengembalikan mutasi lifecycle.
     */
    public function test_audit_failure_rolls_back_lifecycle_transition(): void
    {
        $this->mock(ActivityLogService::class, function ($mock): void {
            $mock->shouldReceive('log')->once()->andThrow(new \RuntimeException('audit unavailable'));
        });

        $this->assertThrows(
            fn () => $this->service()->transition($this->schedule, 'paused', 'Pause dengan audit gagal'),
            \RuntimeException::class,
        );
        $this->assertSame('active', $this->schedule->fresh()->lifecycle_status);
        $this->assertSame(1, DB::table('pm_schedules')->where('id', $this->schedule->id)->where('is_active', true)->count());
        $this->assertSame(0, DB::table('user_activity_logs')->where('record_id', $this->schedule->id)->count());
    }

    /**
     * RED Slice-B Correction 1: reason wajib dan audit ditulis dalam transaksi yang sama
     * dengan mutasi lifecycle, memuat actor, old/new state, dan alasan.
     */
    public function test_transition_requires_reason_and_writes_atomic_audit(): void
    {
        $this->service()->transition($this->schedule, 'paused', 'Operator menunda jadwal untuk inspeksi');

        $this->assertDatabaseHas('user_activity_logs', [
            'module_name' => 'pm_schedule',
            'action' => 'lifecycle_transition',
            'table_name' => 'pm_schedules',
            'record_id' => $this->schedule->id,
            'description' => 'Operator menunda jadwal untuk inspeksi',
        ]);

        $audit = DB::table('user_activity_logs')
            ->where('table_name', 'pm_schedules')
            ->where('record_id', $this->schedule->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame('active', json_decode((string) $audit->old_values, true)['lifecycle_status']);
        $this->assertSame('paused', json_decode((string) $audit->new_values, true)['lifecycle_status']);
        $this->assertFalse((bool) json_decode((string) $audit->new_values, true)['is_active']);
    }

    /**
     * RED Slice-B Correction 3: provenance stale/mismatch ditolak fail-closed.
     */
    public function test_stale_schedule_provenance_cannot_transition_under_wrong_machine_lock(): void
    {
        $otherMachine = Machine::query()->create([
            'location_id' => $this->machine->location_id,
            'machine_code' => 'MC-SLC-OTHER',
            'machine_name' => 'Other Machine',
            'qr_token' => 'qr-slc-other',
            'is_active' => true,
            'lifecycle_status' => 'active',
        ]);

        $otherChecksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-SLC-OTHER',
            'checksheet_name' => 'Other Checksheet',
            'is_active' => true,
        ]);

        $otherAssignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $otherChecksheet->id,
            'machine_id' => $otherMachine->id,
        ]);

        // Simulasi caller basi: setelah caller membaca snapshot schedule yang
        // masih menunjuk assignment mesin pertama, baris schedule dipindah ke
        // assignment mesin lain. Transisi harus ditolak sebelum mutasi.
        PmSchedule::query()->whereKey($this->schedule->id)->update([
            'pm_checksheet_machine_id' => $otherAssignment->id,
        ]);

        $this->assertThrows(
            fn () => $this->service()->transition($this->schedule, 'paused', 'Stale provenance test'),
            InvalidScheduleTransitionException::class,
        );
        $this->assertSame('active', $this->schedule->fresh()->lifecycle_status);
    }

    /**
     * Semua transisi legal mengubah lifecycle_status dan proyeksi is_active
     * pada operasi persist yang sama.
     */
    public function test_legal_schedule_transitions_update_lifecycle_and_compatibility_projection(): void
    {
        $this->service()->transition($this->schedule, 'paused', 'Pause untuk maintenance');
        $this->assertDatabaseHas('pm_schedules', [
            'id' => $this->schedule->id,
            'lifecycle_status' => 'paused',
            'is_active' => 0,
        ]);

        $this->service()->transition($this->schedule->fresh(), 'active', 'Resume setelah maintenance');
        $this->assertDatabaseHas('pm_schedules', [
            'id' => $this->schedule->id,
            'lifecycle_status' => 'active',
            'is_active' => 1,
        ]);

        $this->service()->transition($this->schedule->fresh(), 'ended', 'Era jadwal selesai');
        $this->assertDatabaseHas('pm_schedules', [
            'id' => $this->schedule->id,
            'lifecycle_status' => 'ended',
            'is_active' => 0,
        ]);
    }

    /**
     * Paused dapat langsung diakhiri tanpa harus kembali aktif lebih dulu.
     */
    public function test_paused_schedule_can_be_ended_directly(): void
    {
        $this->service()->transition($this->schedule, 'paused', 'Pause sebelum berakhir');
        $this->service()->transition($this->schedule->fresh(), 'ended', 'Era jadwal selesai');

        $this->assertDatabaseHas('pm_schedules', [
            'id' => $this->schedule->id,
            'lifecycle_status' => 'ended',
            'is_active' => 0,
        ]);
    }

    /**
     * Resume menulis boundary bisnis hari ini tanpa mengubah provenance
     * operasional maupun jendela jadwal.
     */
    public function test_resume_sets_business_boundary_without_rewriting_operational_from(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 09:00:00', 'Asia/Jakarta'));
        $this->schedule->forceFill([
            'lifecycle_status' => 'paused',
            'is_active' => false,
            'effective_live_from' => '2026-09-10',
        ])->save();

        $this->service()->transition($this->schedule->fresh(), 'active', 'Resume setelah maintenance');

        $schedule = $this->schedule->fresh();
        $this->assertSame('2026-09-01', $schedule->operational_from?->toDateString());
        $this->assertSame('2026-09-01', $schedule->start_date?->toDateString());
        $this->assertSame('2026-10-01', $schedule->generate_until?->toDateString());
        $this->assertSame('2026-09-15', $schedule->effective_live_from?->toDateString());
        $this->assertTrue($schedule->is_active);
        $this->assertSame(0, PmScheduleDate::query()->where('pm_schedule_id', $schedule->id)->count());
    }

    /**
     * Pause tidak menulis boundary baru; boundary hanya ditulis saat resume.
     */
    public function test_pause_does_not_write_effective_live_from(): void
    {
        $this->service()->transition($this->schedule, 'paused', 'Pause sementara');

        $this->assertNull($this->schedule->fresh()->effective_live_from);
    }

    /**
     * ENDED bersifat terminal: resume ordinary ditolak dan state tersimpan tidak berubah.
     */
    public function test_ended_schedule_cannot_be_resumed(): void
    {
        $this->service()->transition($this->schedule, 'ended', 'Era jadwal selesai');

        $this->assertThrows(
            fn () => $this->service()->transition($this->schedule->fresh(), 'active', 'Coba hidupkan kembali'),
            InvalidScheduleTransitionException::class,
        );

        $schedule = $this->schedule->fresh();
        $this->assertSame('ended', $schedule->lifecycle_status);
        $this->assertFalse($schedule->is_active);
        $this->assertNull($schedule->effective_live_from);
    }

    /**
     * Transisi berulang dan target/state tidak dikenal ditolak secara deterministik.
     */
    public function test_repeated_and_unknown_transitions_fail_deterministically(): void
    {
        $this->assertThrows(
            fn () => $this->service()->transition($this->schedule, 'active', 'Sudah aktif'),
            InvalidScheduleTransitionException::class,
        );

        $this->service()->transition($this->schedule, 'paused', 'Pause sementara');
        $this->assertThrows(
            fn () => $this->service()->transition($this->schedule->fresh(), 'paused', 'Pause dua kali'),
            InvalidScheduleTransitionException::class,
        );

        $this->assertThrows(
            fn () => $this->service()->transition($this->schedule->fresh(), 'retired', 'State tidak dikenal'),
            InvalidScheduleTransitionException::class,
        );

        $this->assertSame('paused', $this->schedule->fresh()->lifecycle_status);
    }

    /**
     * State terminal dipertahankan meski boolean legacy menyatakan aktif;
     * boolean tidak boleh menghidupkan kembali schedule ENDED.
     */
    public function test_ended_schedule_is_not_revived_by_legacy_boolean_state(): void
    {
        $this->schedule->forceFill([
            'lifecycle_status' => 'ended',
            'is_active' => true,
        ])->save();

        $this->assertThrows(
            fn () => $this->service()->transition($this->schedule->fresh(), 'active', 'Coba hidupkan kembali'),
            InvalidScheduleTransitionException::class,
        );

        $schedule = $this->schedule->fresh();
        $this->assertSame('ended', $schedule->lifecycle_status);
        $this->assertTrue($schedule->is_active);
    }

    /**
     * Execution canonical yang sudah dimulai tetap dapat menyelesaikan review
     * meski Schedule sudah PAUSED, dan kondisinya tidak ditulis ulang.
     */
    public function test_started_execution_and_protected_history_are_preserved(): void
    {
        $date = PmScheduleDate::query()->create([
            'pm_schedule_id' => $this->schedule->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => '2026-09-12',
            'status' => 'in_progress',
        ]);
        $execution = PmExecution::query()->create([
            'pm_schedule_date_id' => $date->id,
            'machine_id' => $this->machine->id,
            'status' => 'in_progress',
            'started_at' => '2026-09-12 08:00:00',
        ]);
        PmExecutionHistory::query()->create([
            'pm_execution_id' => $execution->id,
            'field_name' => 'status',
            'old_value' => null,
            'new_value' => 'in_progress',
            'change_note' => 'fixture history',
        ]);

        // Snapshot histori sebelum transisi lifecycle apa pun.
        $executionBefore = DB::table('pm_executions')->where('id', $execution->id)->first();
        $historyBefore = DB::table('pm_execution_history')->where('pm_execution_id', $execution->id)->orderBy('id')->get()->toArray();
        $dateBefore = DB::table('pm_schedule_dates')->where('id', $date->id)->first();

        $this->service()->transition($this->schedule, 'paused', 'Pause sementara');
        $this->service()->transition($this->schedule->fresh(), 'active', 'Resume setelah maintenance');
        $this->service()->transition($this->schedule->fresh(), 'ended', 'Era jadwal selesai');

        $this->assertEquals($executionBefore, DB::table('pm_executions')->where('id', $execution->id)->first());
        $this->assertEquals($historyBefore, DB::table('pm_execution_history')->where('pm_execution_id', $execution->id)->orderBy('id')->get()->toArray());
        $this->assertEquals($dateBefore, DB::table('pm_schedule_dates')->where('id', $date->id)->first());
    }

    /**
     * Characterization proof: an already started canonical execution remains
     * completable through review approval after Schedule is PAUSED/ENDED.
     * This intentionally tests the smallest existing service-level continuation
     * seam; new-start gating is outside Slice B.
     */
    public function test_started_execution_can_be_approved_after_schedule_end_without_new_execution(): void
    {
        // Fixture executor minimal: satu part dengan standard action agar seam
        // submit produksi dapat memvalidasi item execution.
        $part = PmChecksheetPart::query()->create([
            'pm_checksheet_machine_id' => $this->schedule->pm_checksheet_machine_id,
            'part_name' => 'Motor Drive',
            'is_active' => true,
        ]);
        $standard = PmChecksheetStandard::query()->create([
            'pm_checksheet_part_id' => $part->id,
            'standard_name' => 'Cek suhu motor',
            'input_type' => 'action',
            'action_options' => ['OK', 'NOT OK'],
            'is_required' => true,
            'is_active' => true,
        ]);

        $operator = User::query()->create([
            'name' => 'Lifecycle Operator',
            'username' => 'lifecycle.operator',
            'password' => 'password',
            'role' => 'operator',
            'is_active' => true,
        ]);
        $admin = User::query()->create([
            'name' => 'Lifecycle Review Admin',
            'username' => 'lifecycle.review.admin',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $date = PmScheduleDate::query()->create([
            'pm_schedule_id' => $this->schedule->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => '2026-09-12',
            'status' => 'scheduled',
        ]);

        $request = app('request');
        $executionService = app(PmExecutionService::class);
        $execution = $executionService->startExecutionOnly($request, $this->machine, $operator);
        $this->assertSame('in_progress', $execution->status);

        // Schedule PAUSED: canonical execution yang sudah dimulai harus tetap
        // dapat lanjut ke submit (seam produksi PmExecutionService::submit).
        $this->service()->transition($this->schedule, 'paused', 'Pause untuk inspeksi');

        $submitted = $executionService->submit(
            $request,
            $this->machine,
            $operator,
            [$standard->id => 'OK'],
            [],
            [],
        );
        $this->assertSame('waiting_review', $submitted->status);

        // Schedule ENDED setelah submit: approval tetap boleh menuntaskan
        // pekerjaan yang sudah menunggu review (continuation-only contract).
        $this->service()->transition($this->schedule->fresh(), 'ended', 'Akhiri era jadwal');

        app(PmReviewService::class)->approve(
            $request,
            $submitted->fresh(),
            $admin,
            'Disetujui setelah pekerjaan selesai',
        );

        // Satu canonical execution yang sama menuntung review sampai approved.
        $this->assertSame(
            1,
            PmExecution::query()->withTrashed()->where('pm_schedule_date_id', $date->id)->count(),
        );
        $this->assertDatabaseHas('pm_executions', [
            'id' => $execution->id,
            'pm_schedule_date_id' => $date->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $date->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('pm_execution_history', [
            'pm_execution_id' => $execution->id,
            'field_name' => 'Status PM',
            'old_value' => 'waiting_review',
            'new_value' => 'approved',
        ]);
    }
}
