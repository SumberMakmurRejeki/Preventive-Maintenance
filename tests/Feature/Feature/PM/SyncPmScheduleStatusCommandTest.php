<?php

namespace Tests\Feature\Feature\PM;

use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmExecution;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Models\PrimeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Test terfokus untuk TASK-001 — PM Status Transition Safety.
 *
 * Membuktikan bahwa cron hanya menandai occurrence yang belum dimulai
 * sebagai overdue atau missed, tanpa memutus pekerjaan yang sudah memiliki execution.
 */
class SyncPmScheduleStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    protected Machine $machine;

    protected PmSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $location = Location::query()->create([
            'location_code' => 'LOC-SYNC',
            'location_name' => 'Sync Line',
            'is_active' => true,
        ]);

        $this->machine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'MC-SYNC',
            'machine_name' => 'Sync Machine',
            'qr_token' => 'qr-sync',
            'is_active' => true,
        ]);

        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-SYNC',
            'checksheet_name' => 'Sync Checksheet',
            'is_active' => true,
        ]);

        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);

        $this->schedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'daily',
            'start_date' => now()->subMonth()->toDateString(),
            'generate_until' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ]);
    }

    /**
     * Membuat schedule date dengan status tertentu.
     */
    private function createScheduleDate(string $date, string $status = 'scheduled'): PmScheduleDate
    {
        return PmScheduleDate::query()->create([
            'pm_schedule_id' => $this->schedule->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => $date,
            'status' => $status,
        ]);
    }

    /**
     * Membuat execution pada occurrence tertentu.
     */
    private function createExecution(PmScheduleDate $scheduleDate, bool $softDelete = false): PmExecution
    {
        $execution = PmExecution::query()->create([
            'pm_schedule_date_id' => $scheduleDate->id,
            'machine_id' => $this->machine->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        if ($softDelete) {
            $execution->delete();
        }

        return $execution;
    }

    // ---- Normal Transition: scheduled → overdue ----

    /**
     * Membuktikan scheduled yang sudah melewati batas overdue menjadi overdue
     * dan menghasilkan pm_overdue.
     */
    public function test_scheduled_becomes_overdue_when_past_threshold(): void
    {
        $scheduleDate = $this->createScheduleDate(now()->subDay()->toDateString(), 'scheduled');

        $this->artisan('pm:sync-schedule-status')->assertExitCode(0);

        $scheduleDate->refresh();
        $this->assertSame('overdue', $scheduleDate->status);
        $this->assertDatabaseHas('notifications', [
            'notification_type' => 'pm_overdue',
            'related_table' => 'pm_schedule_dates',
            'related_id' => $scheduleDate->id,
        ]);
    }

    // ---- Normal Transition: overdue → missed ----

    /**
     * Membuktikan overdue yang sudah melewati batas missed menjadi missed
     * dan menghasilkan pm_missed.
     */
    public function test_overdue_becomes_missed_when_past_threshold(): void
    {
        $scheduleDate = $this->createScheduleDate(now()->subDays(2)->toDateString(), 'overdue');

        $this->artisan('pm:sync-schedule-status')->assertExitCode(0);

        $scheduleDate->refresh();
        $this->assertSame('missed', $scheduleDate->status);
        $this->assertDatabaseHas('notifications', [
            'notification_type' => 'pm_missed',
            'related_table' => 'pm_schedule_dates',
            'related_id' => $scheduleDate->id,
        ]);
    }

    // ---- Catch-up: stale scheduled → overdue → missed ----

    /**
     * Membuktikan stale scheduled yang sudah melewati threshold missed
     * tidak langsung menjadi missed, tetapi melewati scheduled → overdue → missed
     * dalam satu command run.
     */
    public function test_stale_scheduled_reaches_missed_through_valid_lifecycle(): void
    {
        $scheduleDate = $this->createScheduleDate(now()->subDays(5)->toDateString(), 'scheduled');

        $this->artisan('pm:sync-schedule-status')->assertExitCode(0);

        $scheduleDate->refresh();
        $this->assertSame('missed', $scheduleDate->status);
    }

    /**
     * Membuktikan catch-up dalam satu command run hanya menghasilkan pm_missed,
     * tidak membuat pm_overdue untuk transition intermediate.
     */
    public function test_catch_up_only_creates_final_missed_notification(): void
    {
        $scheduleDate = $this->createScheduleDate(now()->subDays(5)->toDateString(), 'scheduled');

        $this->artisan('pm:sync-schedule-status')->assertExitCode(0);

        // pm_overdue tidak dibuat untuk catch-up intermediate.
        $this->assertDatabaseMissing('notifications', [
            'notification_type' => 'pm_overdue',
            'related_table' => 'pm_schedule_dates',
            'related_id' => $scheduleDate->id,
        ]);

        // pm_missed adalah satu-satunya notification final.
        $this->assertDatabaseHas('notifications', [
            'notification_type' => 'pm_missed',
            'related_table' => 'pm_schedule_dates',
            'related_id' => $scheduleDate->id,
        ]);
    }

    // ---- Protection: in_progress, waiting_review, approved ----

    /**
     * Membuktikan occurrence in_progress tidak berubah karena cron.
     */
    public function test_in_progress_does_not_become_overdue(): void
    {
        $scheduleDate = $this->createScheduleDate(now()->subDay()->toDateString(), 'in_progress');

        $this->artisan('pm:sync-schedule-status')->assertExitCode(0);

        $scheduleDate->refresh();
        $this->assertSame('in_progress', $scheduleDate->status);
    }

    /**
     * Membuktikan occurrence waiting_review tidak berubah karena cron.
     */
    public function test_waiting_review_does_not_become_missed(): void
    {
        $scheduleDate = $this->createScheduleDate(now()->subDays(5)->toDateString(), 'waiting_review');

        $this->artisan('pm:sync-schedule-status')->assertExitCode(0);

        $scheduleDate->refresh();
        $this->assertSame('waiting_review', $scheduleDate->status);
    }

    /**
     * Membuktikan occurrence approved tidak berubah karena cron.
     */
    public function test_approved_does_not_become_missed(): void
    {
        $scheduleDate = $this->createScheduleDate(now()->subDays(5)->toDateString(), 'approved');

        $this->artisan('pm:sync-schedule-status')->assertExitCode(0);

        $scheduleDate->refresh();
        $this->assertSame('approved', $scheduleDate->status);
    }

    // ---- Protected evidence: active dan soft-deleted execution ----

    /**
     * Membuktikan occurrence dengan execution aktif tidak berubah.
     */
    public function test_occurrence_with_active_execution_is_protected(): void
    {
        $scheduleDate = $this->createScheduleDate(now()->subDay()->toDateString(), 'scheduled');
        $this->createExecution($scheduleDate, softDelete: false);

        $this->artisan('pm:sync-schedule-status')->assertExitCode(0);

        $scheduleDate->refresh();
        $this->assertSame('scheduled', $scheduleDate->status);
    }

    /**
     * Membuktikan occurrence dengan soft-deleted execution juga protected.
     */
    public function test_occurrence_with_soft_deleted_execution_is_protected(): void
    {
        $scheduleDate = $this->createScheduleDate(now()->subDay()->toDateString(), 'scheduled');
        $this->createExecution($scheduleDate, softDelete: true);

        $this->artisan('pm:sync-schedule-status')->assertExitCode(0);

        $scheduleDate->refresh();
        $this->assertSame('scheduled', $scheduleDate->status);
    }

    // ---- Idempotency ----

    /**
     * Membuktikan repeated command tidak menghasilkan transition tambahan.
     */
    public function test_repeated_command_does_not_create_additional_transitions(): void
    {
        $scheduleDate = $this->createScheduleDate(now()->subDay()->toDateString(), 'scheduled');

        $this->artisan('pm:sync-schedule-status')->assertExitCode(0);
        $scheduleDate->refresh();
        $this->assertSame('overdue', $scheduleDate->status);

        $this->artisan('pm:sync-schedule-status')->assertExitCode(0);
        $scheduleDate->refresh();
        $this->assertSame('overdue', $scheduleDate->status);
    }

    /**
     * Membuktikan repeated command tidak menghasilkan duplicate notification.
     */
    public function test_repeated_command_does_not_duplicate_notifications(): void
    {
        $scheduleDate = $this->createScheduleDate(now()->subDay()->toDateString(), 'scheduled');

        $this->artisan('pm:sync-schedule-status')->assertExitCode(0);
        $this->artisan('pm:sync-schedule-status')->assertExitCode(0);

        $this->assertSame(1, PrimeNotification::query()
            ->where('notification_type', 'pm_overdue')
            ->where('related_table', 'pm_schedule_dates')
            ->where('related_id', $scheduleDate->id)
            ->count());
    }

    /**
     * Membuktikan normal transition pada dua waktu berbeda:
     * Hari 1: scheduled → overdue + pm_overdue.
     * Hari 2: overdue → missed + pm_missed, pm_overdue SEBELUMNYA tetap ada.
     */
    public function test_normal_transition_preserves_overdue_notification_when_becoming_missed(): void
    {
        // Menggunakan Carbon::setTestNow() agar deterministic dan tidak bergantung tanggal aktual.
        $day1 = Carbon::create(2026, 8, 19, 0, 0, 0);
        $yesterday = $day1->copy()->subDay();
        Carbon::setTestNow($day1);
        $scheduleDate = $this->createScheduleDate($yesterday->toDateString(), 'scheduled');

        // === Hari 1: scheduled → overdue ===
        Carbon::setTestNow($day1);
        $this->artisan('pm:sync-schedule-status')->assertExitCode(0);

        $scheduleDate->refresh();
        $this->assertSame('overdue', $scheduleDate->status, 'Hari 1: status harus overdue.');
        $this->assertDatabaseHas('notifications', [
            'notification_type' => 'pm_overdue',
            'related_table' => 'pm_schedule_dates',
            'related_id' => $scheduleDate->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notification_type' => 'pm_missed',
            'related_table' => 'pm_schedule_dates',
            'related_id' => $scheduleDate->id,
        ]);
        $this->assertSame(1, PrimeNotification::query()
            ->where('notification_type', 'pm_overdue')
            ->where('related_table', 'pm_schedule_dates')
            ->where('related_id', $scheduleDate->id)
            ->count(), 'Hari 1: tepat satu pm_overdue.');

        // === Hari 2: overdue → missed ===
        // Majukan waktu satu hari agar scheduled_date < (today - 1 day) = kemarin.
        Carbon::setTestNow($day1->copy()->addDay());
        $this->artisan('pm:sync-schedule-status')->assertExitCode(0);

        $scheduleDate->refresh();
        $this->assertSame('missed', $scheduleDate->status, 'Hari 2: status harus missed.');

        // pm_overdue dari Hari 1 TIDAK boleh dihapus.
        $this->assertDatabaseHas('notifications', [
            'notification_type' => 'pm_overdue',
            'related_table' => 'pm_schedule_dates',
            'related_id' => $scheduleDate->id,
        ]);
        $this->assertSame(1, PrimeNotification::query()
            ->where('notification_type', 'pm_overdue')
            ->where('related_table', 'pm_schedule_dates')
            ->where('related_id', $scheduleDate->id)
            ->count(), 'Hari 2: pm_overdue sebelumnya harus tetap ada.');

        // pm_missed baru dibuat.
        $this->assertDatabaseHas('notifications', [
            'notification_type' => 'pm_missed',
            'related_table' => 'pm_schedule_dates',
            'related_id' => $scheduleDate->id,
        ]);
        $this->assertSame(1, PrimeNotification::query()
            ->where('notification_type', 'pm_missed')
            ->where('related_table', 'pm_schedule_dates')
            ->where('related_id', $scheduleDate->id)
            ->count(), 'Hari 2: tepat satu pm_missed.');

        // Kembalikan waktu ke normal.
        Carbon::setTestNow();
    }
}
