<?php

namespace Tests\Feature\Feature\PM;

use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmChecksheetPart;
use App\Models\PmChecksheetStandard;
use App\Models\PmExecution;
use App\Models\PmExecutionItem;
use App\Models\PmExecutionMedia;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Models\User;
use App\Services\Auth\ActivityLogService;
use App\Services\Notification\AdminNotificationService;
use App\Services\PM\PmExecutionMediaService;
use App\Services\PM\PmExecutionService;
use App\Services\PM\PmScheduleDateReconciler;
use App\Services\PM\PmWarningService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PmExecutorTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected Machine $machine;

    protected PmScheduleDate $scheduleDate;

    protected PmChecksheetPart $part;

    protected PmChecksheetStandard $actionStandard;

    protected PmChecksheetStandard $numberStandard;

    protected PmChecksheetStandard $rangeStandard;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $location = Location::query()->create([
            'location_code' => 'LOC-01',
            'location_name' => 'Upcast',
            'is_active' => true,
        ]);

        $this->machine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'UC-001',
            'machine_name' => 'MOTOR SERVO',
            'qr_token' => 'qr-uc-001',
            'is_active' => true,
        ]);

        $this->admin = User::query()->create([
            'name' => 'Admin PRIME',
            'username' => 'admin.prime',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->operator = User::query()->create([
            'name' => 'Operator PRIME',
            'username' => 'operator.prime',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'is_active' => true,
        ]);

        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-CH-UC-001',
            'checksheet_name' => 'Checksheet UC-001',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $checksheetMachine = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
            'created_by' => $this->admin->id,
        ]);

        $this->part = PmChecksheetPart::query()->create([
            'pm_checksheet_machine_id' => $checksheetMachine->id,
            'part_name' => 'Coiling 1',
            'is_active' => true,
        ]);

        $this->actionStandard = PmChecksheetStandard::query()->create([
            'pm_checksheet_part_id' => $this->part->id,
            'standard_name' => 'Kondisi Gearbox',
            'input_type' => 'action',
            'action_options' => ['OK', 'GANTI', 'REPAIR'],
            'is_required' => true,
            'is_active' => true,
        ]);

        $this->numberStandard = PmChecksheetStandard::query()->create([
            'pm_checksheet_part_id' => $this->part->id,
            'standard_name' => 'Suhu Motor',
            'input_type' => 'number',
            'target_value' => 60,
            'unit' => 'celcius',
            'is_required' => true,
            'is_active' => true,
        ]);

        $this->rangeStandard = PmChecksheetStandard::query()->create([
            'pm_checksheet_part_id' => $this->part->id,
            'standard_name' => 'Tekanan',
            'input_type' => 'range',
            'min_value' => 114,
            'max_value' => 190,
            'unit' => 'bar',
            'is_required' => true,
            'is_active' => true,
        ]);

        $schedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $checksheetMachine->id,
            'frequency_type' => 'daily',
            'start_date' => now()->subDay()->toDateString(),
            'generate_until' => now()->addMonth()->toDateString(),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->scheduleDate = PmScheduleDate::query()->create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);
    }

    public function test_operator_can_open_pm_executor_page(): void
    {
        $response = $this->actingAs($this->operator)->get("/pm/executor/{$this->machine->machine_code}");

        $response->assertOk();
        $response->assertSee('PM Executor');
        $response->assertSee('Kondisi Gearbox');
        $response->assertSee('Submit PM');
    }

    public function test_admin_and_guest_cannot_access_pm_executor_page(): void
    {
        $this->actingAs($this->admin)
            ->get("/pm/executor/{$this->machine->machine_code}")
            ->assertRedirect('/403');

        $guestSession = GuestSession::query()->create([
            'guest_name' => 'Guest Prime',
            'session_id' => 'guest-session',
            'login_at' => now(),
        ]);

        $this->withSession([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ])->get("/pm/executor/{$this->machine->machine_code}")
            ->assertRedirect('/403');
    }

    public function test_operator_cannot_open_executor_if_no_active_schedule(): void
    {
        $this->scheduleDate->forceFill([
            'status' => 'approved',
        ])->save();

        $response = $this->actingAs($this->operator)->get("/pm/executor/{$this->machine->machine_code}");

        $response->assertRedirect("/machines/{$this->machine->machine_code}");
    }

    public function test_executor_prefers_reconciled_august_dates_and_keeps_in_progress_precedence(): void
    {
        $location = Location::query()->create([
            'location_code' => 'LOC-UC-018',
            'location_name' => 'Upcast 18',
            'is_active' => true,
        ]);

        $machine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'UC-018',
            'machine_name' => 'REPAIR LINE 18',
            'qr_token' => 'qr-uc-018',
            'is_active' => true,
        ]);

        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-CH-UC-018',
            'checksheet_name' => 'Checksheet UC-018',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $machine->id,
            'created_by' => $this->admin->id,
        ]);

        $schedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'weekly',
            'weekly_days' => [5],
            'operational_from' => '2026-08-01',
            'start_date' => '2026-08-01',
            'generate_until' => '2026-09-01',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        foreach (['2026-06-06', '2026-06-13'] as $date) {
            PmScheduleDate::query()->create([
                'pm_schedule_id' => $schedule->id,
                'machine_id' => $machine->id,
                'scheduled_date' => $date,
                'status' => 'scheduled',
            ]);
        }

        foreach (['2026-08-07', '2026-08-14', '2026-08-21', '2026-08-28'] as $date) {
            PmScheduleDate::query()->create([
                'pm_schedule_id' => $schedule->id,
                'machine_id' => $machine->id,
                'scheduled_date' => $date,
                'status' => 'scheduled',
            ]);
        }

        $reconciler = app(PmScheduleDateReconciler::class);
        $result = $reconciler->reconcile($schedule);

        $this->assertSame(2, $result['removed']);
        $this->assertDatabaseMissing('pm_schedule_dates', [
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $machine->id,
            'scheduled_date' => '2026-06-06',
        ]);

        $service = app(PmExecutionService::class);
        $selected = $service->findScheduleDateForExecutor($machine);

        $this->assertSame('2026-08-07', $selected->scheduled_date->toDateString());
        $this->assertSame('scheduled', $selected->status);

        $historicalInProgress = PmScheduleDate::query()->create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $machine->id,
            'scheduled_date' => '2026-06-20',
            'status' => 'in_progress',
        ]);

        $selected = $service->findScheduleDateForExecutor($machine);

        $this->assertSame($historicalInProgress->id, $selected->id);
        $this->assertSame('2026-06-20', $selected->scheduled_date->toDateString());
    }

    public function test_start_pm_creates_in_progress_execution_and_items(): void
    {
        $response = $this->actingAs($this->operator)->post("/pm/executor/{$this->machine->machine_code}/start", [
            'execution_action' => [
                $this->actionStandard->id => 'OK',
            ],
            'execution_number' => [
                $this->numberStandard->id => 60,
                $this->rangeStandard->id => 120,
            ],
            'part_notes' => [
                $this->part->id => 'Catatan draft part',
            ],
        ]);

        $response->assertRedirect("/pm/executor/{$this->machine->machine_code}");

        $execution = PmExecution::query()->firstOrFail();
        $this->assertSame('in_progress', $execution->status);
        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'status' => 'in_progress',
        ]);
        $this->assertDatabaseCount('pm_execution_items', 3);
    }

    public function test_submit_pm_with_warning_is_allowed_and_creates_notification(): void
    {
        $response = $this->actingAs($this->operator)->post("/pm/executor/{$this->machine->machine_code}/submit", [
            'execution_action' => [
                $this->actionStandard->id => 'OK',
            ],
            'execution_number' => [
                $this->numberStandard->id => 55,
                $this->rangeStandard->id => 220,
            ],
        ]);

        $response->assertRedirect("/machines/{$this->machine->machine_code}");

        $execution = PmExecution::query()->firstOrFail();
        $this->assertSame('waiting_review', $execution->status);
        $this->assertNotNull($execution->submitted_at);

        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'status' => 'waiting_review',
        ]);

        $this->assertDatabaseHas('pm_execution_items', [
            'pm_execution_id' => $execution->id,
            'pm_checksheet_standard_id' => $this->numberStandard->id,
            'is_warning' => 1,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notification_type' => 'pm_waiting_review',
            'related_table' => 'pm_executions',
            'related_id' => $execution->id,
        ]);

        $this->assertTrue(
            PmScheduleDate::query()
                ->where('pm_schedule_id', $this->scheduleDate->pm_schedule_id)
                ->where('machine_id', $this->machine->id)
                ->whereDate('scheduled_date', now()->addDay()->toDateString())
                ->where('status', 'scheduled')
                ->exists(),
        );
    }

    public function test_submit_pm_does_not_duplicate_existing_next_schedule_date(): void
    {
        PmScheduleDate::query()->create([
            'pm_schedule_id' => $this->scheduleDate->pm_schedule_id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => now()->addDay()->toDateString(),
            'status' => 'scheduled',
        ]);

        $this->actingAs($this->operator)->post("/pm/executor/{$this->machine->machine_code}/submit", [
            'execution_action' => [
                $this->actionStandard->id => 'OK',
            ],
            'execution_number' => [
                $this->numberStandard->id => 60,
                $this->rangeStandard->id => 120,
            ],
        ])->assertRedirect("/machines/{$this->machine->machine_code}");

        $this->assertSame(
            1,
            PmScheduleDate::query()
                ->where('pm_schedule_id', $this->scheduleDate->pm_schedule_id)
                ->where('machine_id', $this->machine->id)
                ->whereDate('scheduled_date', now()->addDay()->toDateString())
                ->count(),
        );
    }

    public function test_machine_profile_next_pm_updates_after_submit_when_next_schedule_was_missing(): void
    {
        $this->actingAs($this->operator)->post("/pm/executor/{$this->machine->machine_code}/submit", [
            'execution_action' => [
                $this->actionStandard->id => 'OK',
            ],
            'execution_number' => [
                $this->numberStandard->id => 60,
                $this->rangeStandard->id => 120,
            ],
        ])->assertRedirect("/machines/{$this->machine->machine_code}");

        $this->actingAs($this->operator)
            ->get("/machines/{$this->machine->machine_code}")
            ->assertOk()
            ->assertSee(now()->addDay()->translatedFormat('d M Y'))
            ->assertSee('Scheduled');
    }

    public function test_operator_can_upload_and_delete_pm_media(): void
    {
        $uploadResponse = $this->actingAs($this->operator)->post("/pm/executor/{$this->machine->machine_code}/media/upload", [
            'part_id' => $this->part->id,
            'part_note' => 'Foto kondisi aktual',
            'media_file' => UploadedFile::fake()->image('gearbox.jpg', 800, 600),
        ]);

        $uploadResponse->assertRedirect("/pm/executor/{$this->machine->machine_code}");

        $media = PmExecutionMedia::query()->firstOrFail();
        Storage::disk('public')->assertExists($media->file_path);

        $deleteResponse = $this->actingAs($this->operator)->delete("/pm/executor/{$this->machine->machine_code}/media/{$media->id}");
        $deleteResponse->assertRedirect("/pm/executor/{$this->machine->machine_code}");

        $this->assertDatabaseMissing('pm_execution_media', [
            'id' => $media->id,
        ]);
    }

    /**
     * Memastikan operator dapat menghapus media beserta dua file saat PM aktif.
     */
    public function test_in_progress_media_delete_removes_all_files_and_preserves_active_lifecycle(): void
    {
        $execution = $this->createExecutionWithMedia('in_progress', [
            'started_at' => now()->subMinutes(10),
        ]);
        $media = $execution->media()->firstOrFail();
        $executionHistoryCount = $execution->history()->count();

        app(PmExecutionMediaService::class)->deleteMedia($media);

        Storage::disk('public')->assertMissing($media->file_path);
        Storage::disk('public')->assertMissing($media->original_file_path);
        $this->assertDatabaseMissing('pm_execution_media', ['id' => $media->id]);
        $this->assertDatabaseHas('pm_executions', [
            'id' => $execution->id,
            'status' => 'in_progress',
        ]);
        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'status' => 'in_progress',
        ]);
        $this->assertSame($executionHistoryCount, $execution->history()->count());
    }

    /**
     * Memastikan media pada PM yang menunggu review tidak dapat dimutasi.
     */
    public function test_waiting_review_media_delete_preserves_files_and_execution_state(): void
    {
        $statusChangedAt = now()->subMinutes(5)->seconds(0);
        $execution = $this->createExecutionWithMedia('waiting_review', [
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subMinutes(30),
        ]);
        $this->scheduleDate->forceFill([
            'status' => 'waiting_review',
            'status_changed_at' => $statusChangedAt,
        ])->save();
        $execution->forceFill(['submitted_at' => now()->subMinutes(30)])->save();
        $media = $execution->media()->firstOrFail();
        $before = $execution->fresh();
        $historyCount = $execution->history()->count();

        try {
            app(PmExecutionMediaService::class)->deleteMedia($media);
            $this->fail('Media deletion should be rejected for waiting_review.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Media PM hanya dapat dihapus saat PM sedang dikerjakan.',
                collect($exception->errors())->flatten()->first(),
            );
        }
        Storage::disk('public')->assertExists($media->file_path);
        Storage::disk('public')->assertExists($media->original_file_path);
        $this->assertDatabaseHas('pm_execution_media', ['id' => $media->id]);
        $this->assertSame($before->status, $execution->fresh()->status);
        $this->assertSame($before->submitted_at?->toDateTimeString(), $execution->fresh()->submitted_at?->toDateTimeString());
        $this->assertSame($statusChangedAt->toDateTimeString(), $this->scheduleDate->fresh()->status_changed_at?->toDateTimeString());
        $this->assertSame('waiting_review', $this->scheduleDate->fresh()->status);
        $this->assertSame($historyCount, $execution->history()->count());
    }

    /**
     * Memastikan media pada PM approved tetap menjadi histori yang terlindungi.
     */
    public function test_approved_media_delete_preserves_approval_and_history(): void
    {
        $approvedAt = now()->subMinutes(20)->seconds(0);
        $execution = $this->createExecutionWithMedia('approved', [
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'approved_at' => $approvedAt,
            'approved_by' => $this->admin->id,
            'approved_by_name_snapshot' => $this->admin->name,
            'review_note' => 'Disetujui setelah pemeriksaan.',
        ]);
        $this->scheduleDate->forceFill([
            'status' => 'approved',
            'status_changed_at' => $approvedAt,
        ])->save();
        $media = $execution->media()->firstOrFail();
        $historyCount = $execution->history()->count();
        $before = $execution->fresh();

        try {
            app(PmExecutionMediaService::class)->deleteMedia($media);
            $this->fail('Media deletion should be rejected for approved.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Media PM hanya dapat dihapus saat PM sedang dikerjakan.',
                collect($exception->errors())->flatten()->first(),
            );
        }
        Storage::disk('public')->assertExists($media->file_path);
        Storage::disk('public')->assertExists($media->original_file_path);
        $this->assertDatabaseHas('pm_execution_media', ['id' => $media->id]);
        $after = $execution->fresh();
        $this->assertSame('approved', $after->status);
        $this->assertSame($before->approved_at?->toDateTimeString(), $after->approved_at?->toDateTimeString());
        $this->assertSame($before->approved_by, $after->approved_by);
        $this->assertSame($before->review_note, $after->review_note);
        $this->assertSame('approved', $this->scheduleDate->fresh()->status);
        $this->assertSame($historyCount, $execution->history()->count());
    }

    /**
     * Membuktikan jalur HTTP controller menolak DELETE media pada PM waiting_review
     * sekaligus memastikan seluruh bukti (file, media, execution, schedule, history) tetap utuh.
     */
    public function test_http_delete_media_waiting_review_is_rejected_via_route_and_preserves_evidence(): void
    {
        $statusChangedAt = now()->subMinutes(5)->seconds(0);
        $execution = $this->createExecutionWithMedia('waiting_review', [
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subMinutes(30),
        ]);
        $this->scheduleDate->forceFill([
            'status' => 'waiting_review',
            'status_changed_at' => $statusChangedAt,
        ])->save();
        $media = $execution->media()->firstOrFail();
        $historyCount = $execution->history()->count();
        $before = $execution->fresh();

        $response = $this->actingAs($this->operator)->deleteJson(
            "/pm/executor/{$this->machine->machine_code}/media/{$media->id}",
        );

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Media PM hanya dapat dihapus saat PM sedang dikerjakan.',
        ]);

        Storage::disk('public')->assertExists($media->file_path);
        Storage::disk('public')->assertExists($media->original_file_path);
        $this->assertDatabaseHas('pm_execution_media', ['id' => $media->id]);
        $after = $execution->fresh();
        $this->assertSame($before->status, $after->status);
        $this->assertSame(
            $before->submitted_at?->toDateTimeString(),
            $after->submitted_at?->toDateTimeString(),
        );
        $this->assertSame($statusChangedAt->toDateTimeString(), $this->scheduleDate->fresh()->status_changed_at?->toDateTimeString());
        $this->assertSame('waiting_review', $this->scheduleDate->fresh()->status);
        $this->assertSame($historyCount, $execution->history()->count());
    }

    /**
     * Membuktikan jalur HTTP controller menolak DELETE media pada PM approved
     * tanpa mengubah metadata approval maupun histori.
     */
    public function test_http_delete_media_approved_is_rejected_via_route_and_preserves_evidence(): void
    {
        $approvedAt = now()->subMinutes(20)->seconds(0);
        $execution = $this->createExecutionWithMedia('approved', [
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'approved_at' => $approvedAt,
            'approved_by' => $this->admin->id,
            'approved_by_name_snapshot' => $this->admin->name,
            'review_note' => 'Disetujui setelah pemeriksaan.',
        ]);
        $this->scheduleDate->forceFill([
            'status' => 'approved',
            'status_changed_at' => $approvedAt,
        ])->save();
        $media = $execution->media()->firstOrFail();
        $historyCount = $execution->history()->count();
        $before = $execution->fresh();

        $response = $this->actingAs($this->operator)->deleteJson(
            "/pm/executor/{$this->machine->machine_code}/media/{$media->id}",
        );

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Media PM hanya dapat dihapus saat PM sedang dikerjakan.',
        ]);

        Storage::disk('public')->assertExists($media->file_path);
        Storage::disk('public')->assertExists($media->original_file_path);
        $this->assertDatabaseHas('pm_execution_media', ['id' => $media->id]);
        $after = $execution->fresh();
        $this->assertSame($before->status, $after->status);
        $this->assertSame($before->approved_at?->toDateTimeString(), $after->approved_at?->toDateTimeString());
        $this->assertSame($before->approved_by, $after->approved_by);
        $this->assertSame($before->review_note, $after->review_note);
        $this->assertSame($approvedAt->toDateTimeString(), $this->scheduleDate->fresh()->status_changed_at?->toDateTimeString());
        $this->assertSame('approved', $this->scheduleDate->fresh()->status);
        $this->assertSame($historyCount, $execution->history()->count());
    }

    /**
     * Memastikan authentication, role, dan relasi machine/media tetap dijaga.
     */
    public function test_media_delete_rejects_unauthenticated_wrong_role_and_wrong_machine_without_mutation(): void
    {
        $execution = $this->createExecutionWithMedia('in_progress');
        $media = $execution->media()->firstOrFail();
        $otherMachine = Machine::query()->create([
            'location_id' => $this->machine->location_id,
            'machine_code' => 'UC-002',
            'machine_name' => 'MOTOR SERVO LAIN',
            'qr_token' => 'qr-uc-002',
            'is_active' => true,
        ]);
        $historyCount = $execution->history()->count();

        $this->delete("/pm/executor/{$this->machine->machine_code}/media/{$media->id}")
            ->assertRedirect('/login');
        $this->actingAs($this->admin)
            ->delete("/pm/executor/{$this->machine->machine_code}/media/{$media->id}")
            ->assertRedirect('/403');
        $this->actingAs($this->operator)
            ->delete("/pm/executor/{$otherMachine->machine_code}/media/{$media->id}")
            ->assertNotFound();

        Storage::disk('public')->assertExists($media->file_path);
        Storage::disk('public')->assertExists($media->original_file_path);
        $this->assertDatabaseHas('pm_execution_media', ['id' => $media->id]);
        $this->assertDatabaseHas('pm_executions', ['id' => $execution->id, 'status' => 'in_progress']);
        $this->assertDatabaseHas('pm_schedule_dates', ['id' => $this->scheduleDate->id, 'status' => 'in_progress']);
        $this->assertSame($historyCount, $execution->history()->count());
    }

    /**
     * Memastikan service membaca lifecycle yang tersimpan, bukan model stale.
     */
    public function test_media_delete_rechecks_persisted_lifecycle_after_execution_becomes_waiting_review(): void
    {
        $staleExecution = $this->createExecutionWithMedia('in_progress');
        $media = $staleExecution->media()->firstOrFail();
        PmExecution::query()->whereKey($staleExecution->id)->update(['status' => 'waiting_review']);
        PmScheduleDate::query()->whereKey($this->scheduleDate->id)->update(['status' => 'waiting_review']);
        $historyCount = PmExecution::query()->findOrFail($staleExecution->id)->history()->count();

        try {
            app(PmExecutionMediaService::class)->deleteMedia($media);
            $this->fail('Media deletion should be rejected for the persisted waiting_review state.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Media PM hanya dapat dihapus saat PM sedang dikerjakan.',
                collect($exception->errors())->flatten()->first(),
            );
        }

        Storage::disk('public')->assertExists($media->file_path);
        Storage::disk('public')->assertExists($media->original_file_path);
        $this->assertDatabaseHas('pm_execution_media', ['id' => $media->id]);
        $this->assertDatabaseHas('pm_executions', ['id' => $staleExecution->id, 'status' => 'waiting_review']);
        $this->assertDatabaseHas('pm_schedule_dates', ['id' => $this->scheduleDate->id, 'status' => 'waiting_review']);
        $this->assertSame($historyCount, PmExecution::query()->findOrFail($staleExecution->id)->history()->count());
    }

    /**
     * Test A — submit dengan lifecycle tersimpan yang sudah keluar dari in_progress
     * ditolak oleh canonical resolver sebelum mutasi item/media apapun.
     *
     * ADR-004: caller snapshot/context tidak menjadi sumber keputusan; resolver
     * selalu membaca ulang occurrence dan execution dari database dengan lock,
     * lalu menolak waiting_review sebagai occurrence yang sudah diproses.
     */
    public function test_stale_submit_is_rejected_before_any_draft_mutation(): void
    {
        // Execution in_progress dibuat sebagai kondisi awal; setelah ini status
        // database diubah ke waiting_review sehingga request berikutnya harus
        // melihat kondisi persisted, bukan kondisi in-memory yang lama.
        $execution = $this->createExecutionWithMedia('in_progress', ['started_at' => now()->subMinutes(5)]);

        // Ubah status di database setelah kondisi awal dibuat; request stale
        // tidak boleh memakai snapshot in_progress yang sudah tidak valid.
        PmExecution::query()->whereKey($execution->id)->update(['status' => 'waiting_review']);

        // Snapshot jumlah item/media sebelum request untuk membuktikan zero mutation.
        $staleItemCount = \DB::table('pm_execution_items')->where('pm_execution_id', $execution->id)->count();
        $staleMediaCount = $execution->media()->count();

        try {
            app(PmExecutionService::class)->startOrSaveDraft(
                request: Request::create('/'),
                machine: $this->machine,
                operator: $this->operator,
                actionValues: [$this->actionStandard->id => 'OK'],
                numberValues: [$this->numberStandard->id => '60', $this->rangeStandard->id => '120'],
                partNotes: [],
            );
            $this->fail('Diharapkan ValidationException saat lifecycle persisted tidak in_progress.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'PM untuk occurrence ini sudah diproses dan tidak dapat dimulai ulang.',
                collect($e->errors())->flatten()->first(),
            );
        }

        // Tidak ada item atau media baru yang dibuat; lifecycle tidak berubah.
        $this->assertSame($staleItemCount, (int) \DB::table('pm_execution_items')->where('pm_execution_id', $execution->id)->count());
        $this->assertSame($staleMediaCount, $execution->media()->count());
        $this->assertDatabaseHas('pm_executions', ['id' => $execution->id, 'status' => 'waiting_review']);
    }

    /**
     * Memastikan part dari context occurrence lain tidak dapat ditempelkan ke media.
     */
    public function test_media_upload_rejects_part_from_different_occurrence_checksheet(): void
    {
        $execution = $this->createExecutionWithMedia('in_progress');
        $otherChecksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-CH-OTHER',
            'checksheet_name' => 'Checksheet Occurrence Lain',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
        $otherChecksheetMachine = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $otherChecksheet->id,
            'machine_id' => $this->machine->id,
            'created_by' => $this->admin->id,
        ]);
        $otherPart = PmChecksheetPart::query()->create([
            'pm_checksheet_machine_id' => $otherChecksheetMachine->id,
            'part_name' => 'Part Occurrence Lain',
            'is_active' => true,
        ]);
        $filesBefore = Storage::disk('public')->files('pm-execution-media');

        try {
            app(PmExecutionMediaService::class)->storeForExecution(
                execution: $execution,
                part: $otherPart,
                file: UploadedFile::fake()->image('wrong-part.jpg', 640, 480),
                operator: $this->operator,
                note: 'Part dari occurrence lain.',
            );
            $this->fail('Part dari checksheet occurrence lain seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Part media tidak sesuai dengan checksheet occurrence PM.',
                collect($exception->errors())->flatten()->first(),
            );
        }

        // Tidak boleh ada row atau file ketika ownership part gagal diverifikasi.
        $this->assertDatabaseMissing('pm_execution_media', [
            'pm_execution_id' => $execution->id,
            'pm_checksheet_part_id' => $otherPart->id,
        ]);
        $this->assertSame($filesBefore, Storage::disk('public')->files('pm-execution-media'));
    }

    /**
     * Test A — upload media ditolak saat lifecycle tersimpan sudah keluar dari in_progress,
     * tanpa menyisakan baris media maupun file baru di storage.
     */
    public function test_stale_upload_is_rejected_without_persisting_media_row_or_file(): void
    {
        $staleExecution = $this->createExecutionWithMedia('in_progress', ['started_at' => now()->subMinutes(5)]);

        // Submit bersamaan memindahkan status tersimpan setelah kandidat execution diambil.
        PmExecution::query()->whereKey($staleExecution->id)->update(['status' => 'waiting_review']);
        PmScheduleDate::query()->whereKey($this->scheduleDate->id)->update(['status' => 'waiting_review']);

        $mediaCount = PmExecutionMedia::query()->where('pm_execution_id', $staleExecution->id)->count();
        $filesBefore = Storage::disk('public')->files('pm-execution-media');

        try {
            app(PmExecutionMediaService::class)->storeForExecution(
                execution: $staleExecution,
                part: $this->part,
                file: UploadedFile::fake()->image('stale-upload.jpg', 640, 480),
                operator: $this->operator,
                note: 'Upload saat PM sudah menunggu review.',
            );
            $this->fail('Upload media seharusnya ditolak untuk status waiting_review yang tersimpan.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Media PM hanya dapat diunggah saat PM sedang dikerjakan.',
                collect($exception->errors())->flatten()->first(),
            );
        }

        $this->assertSame(
            $mediaCount,
            PmExecutionMedia::query()->where('pm_execution_id', $staleExecution->id)->count(),
        );
        $this->assertSame($filesBefore, Storage::disk('public')->files('pm-execution-media'));
        $this->assertDatabaseHas('pm_executions', ['id' => $staleExecution->id, 'status' => 'waiting_review']);
    }

    /**
     * Test F — kegagalan database selama deleteMedia memutar balik baris dan tidak membersihkan filesystem.
     * Satu-shot deleting observer pada PmExecutionMedia melempar RuntimeException di dalam transaksi.
     */
    public function test_db_failure_during_media_delete_rolls_back_row_and_skips_filesystem_cleanup(): void
    {
        $execution = $this->createExecutionWithMedia('in_progress', ['started_at' => now()->subMinutes(5)]);
        $media = $execution->media()->firstOrFail();

        // Daftarkan observer sekali pakai yang memaksa kegagalan DB di dalam transaksi.
        PmExecutionMedia::deleting(function (): void {
            throw new \RuntimeException('Simulasi kegagalan DB saat delete media.');
        });

        try {
            try {
                app(PmExecutionMediaService::class)->deleteMedia($media);
                $this->fail('Harus melempar exception saat DB gagal.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Simulasi kegagalan DB', $e->getMessage());
            }
        } finally {
            // Hapus seluruh listener pada model agar tidak bocor ke test lain.
            PmExecutionMedia::flushEventListeners();
        }

        // Baris media dan file tetap utuh karena transaksi di-rollback.
        $this->assertDatabaseHas('pm_execution_media', ['id' => $media->id]);
        Storage::disk('public')->assertExists($media->file_path);
        Storage::disk('public')->assertExists($media->original_file_path);
        $this->assertDatabaseHas('pm_executions', ['id' => $execution->id, 'status' => 'in_progress']);
    }

    /**
     * Test G — kegagalan cleanup filesystem setelah commit dicatat sebagai warning
     * tanpa membatalkan lifecycle yang sudah commit di database.
     * Pola: Storage::shouldReceive('disk') dengan disk mock dari Mockery, sesuai konvensi MachineService.
     */
    public function test_filesystem_cleanup_failure_after_media_delete_commit_logs_warning_without_lifecycle_rejection(): void
    {
        $execution = $this->createExecutionWithMedia('in_progress', ['started_at' => now()->subMinutes(5)]);
        $media = $execution->media()->firstOrFail();
        $filePath = $media->file_path;
        $originalFilePath = $media->original_file_path;

        // Mock disk: file_path mengembalikan false, original_file_path melempar exception.
        $diskMock = \Mockery::mock();
        $diskMock->shouldReceive('delete')
            ->once()
            ->with($filePath)
            ->andReturnFalse();
        $diskMock->shouldReceive('delete')
            ->once()
            ->with($originalFilePath)
            ->andThrow(new \RuntimeException('Disk I/O gagal pada cleanup.'));

        // Paksa Storage::disk('public') mengembalikan mock, sesuai konvensi MachineService.
        Storage::shouldReceive('disk')
            ->twice()
            ->with('public')
            ->andReturn($diskMock);

        Log::spy();

        // deleteMedia tidak boleh melempar; lifecycle tetap committed.
        app(PmExecutionMediaService::class)->deleteMedia($media);

        // Baris media terhapus dari database meskipun cleanup filesystem gagal.
        $this->assertDatabaseMissing('pm_execution_media', ['id' => $media->id]);
        $this->assertDatabaseHas('pm_executions', ['id' => $execution->id, 'status' => 'in_progress']);

        // Dua warning harus dicatat: satu false-return, satu exception.
        Log::shouldHaveReceived('warning')
            ->twice();
    }

    // ====================================================================
    // ADR-004 Slice 1 — Canonical Serialization + Lifecycle Contract
    // ====================================================================

    /**
     * S1-01: Start terjadwal pertama → tepat satu execution, occurrence in_progress.
     */
    public function test_s1_first_scheduled_start_creates_exactly_one_execution(): void
    {
        $service = app(PmExecutionService::class);
        $execution = $service->startExecutionOnly(
            request: $this->s1Request(),
            machine: $this->machine,
            operator: $this->operator,
        );

        $this->assertSame('in_progress', $execution->status);
        $this->assertSame(1, PmExecution::query()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
        $this->assertSame('in_progress', $this->scheduleDate->fresh()->status);
    }

    /**
     * S1-02: Operator yang sama retry → reuse execution ID, count tetap 1.
     */
    public function test_s1_same_operator_retry_reuses_execution(): void
    {
        $service = app(PmExecutionService::class);
        $first = $service->startExecutionOnly(
            request: $this->s1Request(),
            machine: $this->machine,
            operator: $this->operator,
        );
        $second = $service->startExecutionOnly(
            request: $this->s1Request(),
            machine: $this->machine,
            operator: $this->operator,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PmExecution::query()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
    }

    /**
     * S1-03: Operator berbeda retry → reuse ID yang sama, operator asli tetap.
     */
    public function test_s1_different_operator_retry_reuses_same_execution_preserves_original_operator(): void
    {
        $service = app(PmExecutionService::class);
        $first = $service->startExecutionOnly(
            request: $this->s1Request(),
            machine: $this->machine,
            operator: $this->operator,
        );

        $otherOperator = User::query()->create([
            'name' => 'Operator Lain',
            'username' => 'operator.lain',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'is_active' => true,
        ]);

        $second = $service->startExecutionOnly(
            request: $this->s1Request(),
            machine: $this->machine,
            operator: $otherOperator,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame($this->operator->id, $second->fresh()->operator_id);
        $this->assertSame(1, PmExecution::query()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
    }

    /**
     * S1-04: Start, submit, media-upload semua menggunakan canonical resolver yang sama.
     * Dibuktikan melalui startOrSaveDraft yang memanggil startExecutionOnly,
     * dan submit yang memanggil startOrSaveDraft — ketiganya menghasilkan execution sama.
     */
    public function test_s1_all_entry_points_use_same_canonical_resolver(): void
    {
        $service = app(PmExecutionService::class);

        // Entry point 1: startExecutionOnly (start)
        $exec1 = $service->startExecutionOnly(
            request: $this->s1Request(),
            machine: $this->machine,
            operator: $this->operator,
        );

        // Entry point 2: startOrSaveDraft (draft)
        $exec2 = $service->startOrSaveDraft(
            request: $this->s1Request(),
            machine: $this->machine,
            operator: $this->operator,
            actionValues: [$this->actionStandard->id => 'OK'],
            numberValues: [$this->numberStandard->id => '60', $this->rangeStandard->id => '120'],
            partNotes: [],
        );

        $this->assertSame($exec1->id, $exec2->id);
        $this->assertSame(1, PmExecution::query()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
    }

    /**
     * S1-05: Existing in_progress execution tetap digunakan ulang.
     */
    public function test_s1_existing_in_progress_reused(): void
    {
        $existing = $this->createExecutionWithMedia('in_progress', ['started_at' => now()->subMinutes(10)]);

        $service = app(PmExecutionService::class);
        $resolved = $service->startExecutionOnly(
            request: $this->s1Request(),
            machine: $this->machine,
            operator: $this->operator,
        );

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame(1, PmExecution::query()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
    }

    /**
     * S1-06: waiting_review menolak start (zero mutation).
     */
    public function test_s1_waiting_review_rejects_start_with_zero_mutation(): void
    {
        $existing = $this->createExecutionWithMedia('waiting_review', [
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subMinutes(30),
        ]);
        // Occurrence stale/eligible: resolver must reject from execution lifecycle.
        $this->scheduleDate->forceFill([
            'status' => 'scheduled',
            'status_changed_at' => null,
        ])->save();

        $service = app(PmExecutionService::class);

        try {
            $service->startExecutionOnly(
                request: $this->s1Request(),
                machine: $this->machine,
                operator: $this->operator,
            );
            $this->fail('Start harus ditolak saat ada execution waiting_review.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(
                'sudah',
                collect($e->errors())->flatten()->first(),
            );
        }

        $this->assertSame(1, PmExecution::query()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
        $this->assertSame('waiting_review', $existing->fresh()->status);
    }

    /**
     * S1-07: approved menolak start (zero mutation).
     */
    public function test_s1_approved_rejects_start_with_zero_mutation(): void
    {
        $existing = $this->createExecutionWithMedia('approved', [
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'approved_at' => now()->subMinutes(30),
            'approved_by' => $this->admin->id,
            'approved_by_name_snapshot' => $this->admin->name,
        ]);
        // Occurrence stale/eligible: resolver must reject from execution lifecycle.
        $this->scheduleDate->forceFill([
            'status' => 'scheduled',
            'status_changed_at' => null,
        ])->save();

        $service = app(PmExecutionService::class);

        try {
            $service->startExecutionOnly(
                request: $this->s1Request(),
                machine: $this->machine,
                operator: $this->operator,
            );
            $this->fail('Start harus ditolak saat ada execution approved.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(
                'sudah',
                collect($e->errors())->flatten()->first(),
            );
        }

        $this->assertSame(1, PmExecution::query()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
        $this->assertSame('approved', $existing->fresh()->status);
    }

    /**
     * S1-08: Soft-deleted execution memblokir pembuatan baru (bukti historis).
     */
    public function test_s1_soft_deleted_execution_blocks_replacement(): void
    {
        $existing = PmExecution::query()->create([
            'pm_schedule_date_id' => $this->scheduleDate->id,
            'machine_id' => $this->machine->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'in_progress',
            'started_at' => now()->subHour(),
        ]);
        $existing->delete(); // soft-delete

        $service = app(PmExecutionService::class);

        try {
            $service->startExecutionOnly(
                request: $this->s1Request(),
                machine: $this->machine,
                operator: $this->operator,
            );
            $this->fail('Start harus ditolak saat ada soft-deleted execution.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(
                'histori',
                mb_strtolower(collect($e->errors())->flatten()->first()),
            );
        }

        // Tetap hanya satu execution (yang soft-deleted)
        $this->assertSame(1, PmExecution::withTrashed()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
        $this->assertSame(0, PmExecution::query()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
    }

    /**
     * S1-09: Legacy duplicate (>1 rows) fail closed — tidak memilih newest/oldest.
     */
    public function test_s1_legacy_duplicate_executions_fail_closed(): void
    {
        // S2: named unique index membuat row duplikat tidak mungkin dibuat lewat jalur normal.
        // Test legacy ini tetap memvalidasi kondisi pre-constraint dengan dua row nyata:
        // turunkan sementara hanya named unique index, pulihkan lewat finally di akhir test.
        Schema::table('pm_executions', function (Blueprint $table): void {
            $table->dropUnique('pm_executions_pm_schedule_date_id_unique');
        });

        $first = null;
        $second = null;

        try {
            // Buat dua execution secara manual untuk simulasi data legacy
            $first = PmExecution::query()->create([
                'pm_schedule_date_id' => $this->scheduleDate->id,
                'machine_id' => $this->machine->id,
                'operator_id' => $this->operator->id,
                'operator_name_snapshot' => $this->operator->name,
                'status' => 'in_progress',
                'started_at' => now()->subHour(),
            ]);
            $second = PmExecution::query()->create([
                'pm_schedule_date_id' => $this->scheduleDate->id,
                'machine_id' => $this->machine->id,
                'operator_id' => $this->operator->id,
                'operator_name_snapshot' => $this->operator->name,
                'status' => 'in_progress',
                'started_at' => now()->subMinutes(30),
            ]);
            $this->scheduleDate->forceFill(['status' => 'in_progress', 'status_changed_at' => now()])->save();

            $service = app(PmExecutionService::class);

            try {
                $service->startExecutionOnly(
                    request: $this->s1Request(),
                    machine: $this->machine,
                    operator: $this->operator,
                );
                $this->fail('Legacy duplicate harus gagal closed.');
            } catch (ValidationException $e) {
                $this->assertStringContainsString(
                    'duplikat',
                    mb_strtolower(collect($e->errors())->flatten()->first()),
                );
            }
        } finally {
            // Hard delete fixture duplikat (row soft-deleted pun tetap menempati unique index),
            // lalu pulihkan named unique index agar invariant Slice 2 tidak bocor ke test lain.
            if ($first !== null || $second !== null) {
                PmExecution::withTrashed()
                    ->whereIn('id', array_filter([$first?->id, $second?->id]))
                    ->forceDelete();
            }

            Schema::table('pm_executions', function (Blueprint $table): void {
                $table->unique('pm_schedule_date_id', 'pm_executions_pm_schedule_date_id_unique');
            });
        }
    }

    /**
     * S1-11: Machine mismatch tetap terdeteksi berdasarkan schedule-date identity.
     * Resolver tidak boleh membuat replacement untuk row historis yang salah parent.
     */
    public function test_s1_machine_mismatch_execution_blocks_replacement(): void
    {
        $otherMachine = Machine::query()->create([
            'location_id' => $this->machine->location_id,
            'machine_code' => 'UC-002',
            'machine_name' => 'MOTOR SERVO B',
            'qr_token' => 'qr-uc-002',
            'is_active' => true,
        ]);

        $existing = PmExecution::query()->create([
            'pm_schedule_date_id' => $this->scheduleDate->id,
            'machine_id' => $otherMachine->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'in_progress',
            'started_at' => now()->subHour(),
        ]);

        $service = app(PmExecutionService::class);

        try {
            $service->startExecutionOnly(
                request: $this->s1Request(),
                machine: $this->machine,
                operator: $this->operator,
            );
            $this->fail('Start harus ditolak saat machine execution mismatch dengan occurrence.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(
                'tidak sesuai',
                mb_strtolower(collect($e->errors())->flatten()->first()),
            );
        }

        $this->assertDatabaseHas('pm_executions', [
            'id' => $existing->id,
            'pm_schedule_date_id' => $this->scheduleDate->id,
            'machine_id' => $otherMachine->id,
            'status' => 'in_progress',
        ]);
        $this->assertSame(
            1,
            PmExecution::withTrashed()->where('pm_schedule_date_id', $this->scheduleDate->id)->count(),
        );
    }

    /**
     * Membuat Request dengan session store agar ActivityLogService dapat dipanggil
     * langsung dari service tanpa middleware web penuh (ADR-004 Slice 1).
     */
    private function s1Request(): Request
    {
        $request = Request::create('/', 'GET');
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    /**
     * S1-10: canonicalExecution() relation pada PmScheduleDate mengembalikan single row termasuk soft-deleted.
     */
    public function test_s1_canonical_execution_relation_includes_soft_deleted(): void
    {
        $execution = PmExecution::query()->create([
            'pm_schedule_date_id' => $this->scheduleDate->id,
            'machine_id' => $this->machine->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'in_progress',
            'started_at' => now()->subHour(),
        ]);

        // Sebelum soft-delete
        $canonical = $this->scheduleDate->canonicalExecution;
        $this->assertNotNull($canonical);
        $this->assertSame($execution->id, $canonical->id);

        // Setelah soft-delete
        $execution->delete();
        $fresh = $this->scheduleDate->fresh();
        $this->assertNotNull($fresh->canonicalExecution);
        $this->assertSame($execution->id, $fresh->canonicalExecution->id);
    }

    /**
     * S1-11: Authorization tetap tidak berubah — admin dan guest tetap ditolak.
     */
    public function test_s1_authorization_unchanged(): void
    {
        // Admin cannot start
        $this->actingAs($this->admin)
            ->post("/pm/executor/{$this->machine->machine_code}/start", [
                'execution_action' => [$this->actionStandard->id => 'OK'],
                'execution_number' => [
                    $this->numberStandard->id => 60,
                    $this->rangeStandard->id => 120,
                ],
            ])
            ->assertRedirect('/403');

        $this->assertSame(0, PmExecution::query()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
    }

    /**
     * S1-12: Occurrence transition + next-date behavior tidak berubah setelah canonical resolver.
     */
    public function test_s1_occurrence_transition_and_next_date_unchanged(): void
    {
        // Submit membuat execution, transisi ke waiting_review, buat jadwal berikutnya
        $this->actingAs($this->operator)->post("/pm/executor/{$this->machine->machine_code}/submit", [
            'execution_action' => [$this->actionStandard->id => 'OK'],
            'execution_number' => [
                $this->numberStandard->id => 60,
                $this->rangeStandard->id => 120,
            ],
        ])->assertRedirect("/machines/{$this->machine->machine_code}");

        $execution = PmExecution::query()->firstOrFail();
        $this->assertSame('waiting_review', $execution->status);
        $this->assertSame('waiting_review', $this->scheduleDate->fresh()->status);

        // Next schedule date harus ada
        $this->assertTrue(
            PmScheduleDate::query()
                ->where('pm_schedule_id', $this->scheduleDate->pm_schedule_id)
                ->where('machine_id', $this->machine->id)
                ->whereDate('scheduled_date', now()->addDay()->toDateString())
                ->where('status', 'scheduled')
                ->exists(),
        );
    }

    /**
     * Slice A: eksekusi baru menangkap identitas mesin, lokasi, dan checksheet dari parent authoritative.
     */
    public function test_slice_a_new_execution_persists_transaction_identity_snapshots(): void
    {
        $execution = app(PmExecutionService::class)->startExecutionOnly(
            request: $this->s1Request(),
            machine: $this->machine,
            operator: $this->operator,
        );

        $this->assertSame('UC-001', $execution->machine_code_snapshot);
        $this->assertSame('MOTOR SERVO', $execution->machine_name_snapshot);
        $this->assertSame('LOC-01', $execution->location_code_snapshot);
        $this->assertSame('Upcast', $execution->location_name_snapshot);
        $this->assertSame('PM-CH-UC-001', $execution->checksheet_code_snapshot);
        $this->assertSame('Checksheet UC-001', $execution->checksheet_name_snapshot);
        $this->assertSame($this->machine->id, $execution->machine_id);
        $this->assertSame($this->scheduleDate->id, $execution->pm_schedule_date_id);
    }

    /**
     * Slice A: reuse canonical tidak pernah menulis ulang snapshot transaction identity.
     */
    public function test_slice_a_canonical_reuse_keeps_original_identity_snapshots(): void
    {
        $service = app(PmExecutionService::class);
        $first = $service->startExecutionOnly($this->s1Request(), $this->machine, $this->operator);
        $original = $first->only([
            'machine_code_snapshot', 'machine_name_snapshot',
            'location_code_snapshot', 'location_name_snapshot',
            'checksheet_code_snapshot', 'checksheet_name_snapshot',
        ]);

        $this->machine->forceFill(['machine_code' => 'UC-RENAMED', 'machine_name' => 'MOTOR RENAMED'])->save();
        $this->machine->location->forceFill(['location_code' => 'LOC-RENAMED', 'location_name' => 'Lokasi Renamed'])->save();
        $this->scheduleDate->schedule->checksheetMachine->checksheet->forceFill([
            'checksheet_code' => 'PM-RENAMED',
            'checksheet_name' => 'Checksheet Renamed',
        ])->save();

        $second = $service->startExecutionOnly($this->s1Request(), $this->machine->fresh(), $this->operator);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($original, $second->fresh()->only(array_keys($original)));
    }

    /**
     * Slice A: legacy row dengan snapshot NULL tetap dapat dibaca tanpa backfill.
     */
    public function test_slice_a_legacy_execution_with_null_snapshots_remains_readable(): void
    {
        $execution = PmExecution::query()->create([
            'pm_schedule_date_id' => $this->scheduleDate->id,
            'machine_id' => $this->machine->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $loaded = PmExecution::query()->findOrFail($execution->id);
        $this->assertNull($loaded->machine_code_snapshot);
        $this->assertNull($loaded->checksheet_name_snapshot);
    }

    /**
     * Slice A: lokasi authoritative yang hilang menghentikan creation sebelum insert.
     */
    public function test_slice_a_missing_location_fails_closed_before_new_execution_insert(): void
    {
        $this->machine->location->delete();

        try {
            app(PmExecutionService::class)->startExecutionOnly($this->s1Request(), $this->machine, $this->operator);
            $this->fail('Lokasi soft-deleted harus menolak execution baru.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('lokasi', mb_strtolower(collect($e->errors())->flatten()->first()));
        }

        $this->assertSame(0, PmExecution::withTrashed()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
    }

    /**
     * Slice A: checksheet trashed (soft-deleted) gagal closed sebelum insert execution baru.
     */
    public function test_slice_a_missing_checksheet_fails_closed_before_new_execution_insert(): void
    {
        $checksheet = $this->scheduleDate->schedule->checksheetMachine->checksheet;
        $checksheet->delete();

        try {
            app(PmExecutionService::class)->startExecutionOnly($this->s1Request(), $this->machine, $this->operator);
            $this->fail('Checksheet soft-deleted harus menolak execution baru.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('checksheet', mb_strtolower(collect($e->errors())->flatten()->first()));
        }

        $this->assertSame(0, PmExecution::withTrashed()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
    }

    /**
     * Slice A: assignment provenance mismatch (checksheetMachine.machine_id ≠ locked Machine.id)
     * fail closed sebelum insert execution baru.
     */
    public function test_slice_a_assignment_machine_mismatch_fails_closed(): void
    {
        $otherMachine = Machine::query()->create([
            'location_id' => $this->machine->location_id,
            'machine_code' => 'UC-MISMATCH',
            'machine_name' => 'MOTOR Mismatch',
            'qr_token' => 'qr-uc-mismatch',
            'is_active' => true,
        ]);
        $this->scheduleDate->schedule->checksheetMachine->forceFill(['machine_id' => $otherMachine->id])->save();

        try {
            app(PmExecutionService::class)->startExecutionOnly($this->s1Request(), $this->machine, $this->operator);
            $this->fail('Assignment machine mismatch harus menolak execution baru.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('tidak sesuai', mb_strtolower(collect($e->errors())->flatten()->first()));
        }

        $this->assertSame(0, PmExecution::withTrashed()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
    }

    /**
     * Membuat execution dan dua file media deterministik untuk assertion lifecycle.
     */
    private function createExecutionWithMedia(string $status, array $overrides = []): PmExecution
    {
        $execution = PmExecution::query()->create(array_merge([
            'pm_schedule_date_id' => $this->scheduleDate->id,
            'machine_id' => $this->machine->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => $status,
        ], $overrides));
        PmScheduleDate::query()->whereKey($this->scheduleDate->id)->update([
            'status' => $status,
            'status_changed_at' => now(),
        ]);
        $filePath = "pm-execution-media/{$status}-compressed.jpg";
        $originalFilePath = "pm-execution-media/{$status}-original.jpg";
        Storage::disk('public')->put($filePath, 'compressed-content');
        Storage::disk('public')->put($originalFilePath, 'original-content');

        $execution->media()->create([
            'pm_checksheet_part_id' => $this->part->id,
            'part_name_snapshot' => $this->part->part_name,
            'file_type' => 'photo',
            'file_path' => $filePath,
            'original_file_path' => $originalFilePath,
            'file_name' => "{$status}.jpg",
            'mime_type' => 'image/jpeg',
            'file_size' => 18,
            'original_file_size' => 16,
            'compressed_file_size' => 18,
            'note' => 'Catatan media '.$status,
            'uploaded_by' => $this->operator->id,
            'uploaded_by_name_snapshot' => $this->operator->name,
        ]);

        return $execution;
    }

    /**
     * Membuat exception duplicate-key realistis tanpa menjalankan migration Slice 2.
     */
    private function makeUniqueViolation(string $indexName, string $table): UniqueConstraintViolationException
    {
        $duplicateMessage = "Duplicate entry '1' for key '{$indexName}'";
        $previous = new \PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 {$duplicateMessage}", 23000);
        $previous->errorInfo = ['23000', 1062, $duplicateMessage];

        return new UniqueConstraintViolationException(
            'mysql',
            "insert into `{$table}` (`pm_schedule_date_id`, `machine_id`) values (?, ?)",
            [1, 1],
            $previous,
        );
    }

    /**
     * Seam deterministik untuk mensimulasikan competitor yang belum terlihat
     * pada pembacaan pertama, lalu winner sudah tersedia saat fallback.
     */
    private function collisionService(UniqueConstraintViolationException $violation, bool $hideCanonicalAlways = false): PmExecutionService
    {
        return new class(app(PmWarningService::class), app(PmExecutionMediaService::class), app(ActivityLogService::class), app(AdminNotificationService::class), $violation, $hideCanonicalAlways) extends PmExecutionService
        {
            public int $canonicalReads = 0;

            public int $insertAttempts = 0;

            public function __construct(
                PmWarningService $warningService,
                PmExecutionMediaService $mediaService,
                ActivityLogService $activityLogService,
                AdminNotificationService $notificationService,
                private UniqueConstraintViolationException $violation,
                private bool $hideCanonicalAlways,
            ) {
                parent::__construct($warningService, $mediaService, $activityLogService, $notificationService);
            }

            protected function findCanonicalExecution(Machine $machine, PmScheduleDate $scheduleDate, bool $lock = false): ?PmExecution
            {
                $this->canonicalReads++;

                if ($this->hideCanonicalAlways || $this->canonicalReads === 1) {
                    return null;
                }

                return parent::findCanonicalExecution($machine, $scheduleDate, $lock);
            }

            protected function createExecutionForStart(PmScheduleDate $scheduleDate, Machine $machine, User $operator): PmExecution
            {
                $this->insertAttempts++;

                throw $this->violation;
            }
        };
    }

    /**
     * S1-13: named canonical collision rollback-safe dan kembali ke winner canonical.
     */
    public function test_s1_canonical_unique_collision_recovers_to_existing_execution(): void
    {
        $canonical = PmExecution::query()->create([
            'pm_schedule_date_id' => $this->scheduleDate->id,
            'machine_id' => $this->machine->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(2),
        ]);
        $service = $this->collisionService($this->makeUniqueViolation('pm_executions_pm_schedule_date_id_unique', 'pm_executions'));

        $execution = $service->startExecutionOnly($this->s1Request(), $this->machine, $this->operator);

        $this->assertSame(1, $service->insertAttempts);
        $this->assertSame($canonical->id, $execution->id);
        $this->assertSame(1, PmExecution::withTrashed()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
    }

    /**
     * S1-14: duplicate-key lain langsung melempar object exception asli.
     */
    public function test_s1_unrelated_unique_violation_is_rethrown_without_collision_recovery(): void
    {
        PmExecution::query()->create([
            'pm_schedule_date_id' => $this->scheduleDate->id,
            'machine_id' => $this->machine->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(2),
        ]);
        $violation = $this->makeUniqueViolation('users_username_unique', 'users');
        $service = $this->collisionService($violation);

        try {
            $service->startExecutionOnly($this->s1Request(), $this->machine, $this->operator);
            $this->fail('Unique violation unrelated harus dilempar ulang.');
        } catch (UniqueConstraintViolationException $caught) {
            $this->assertSame($violation, $caught);
        }

        $this->assertSame(1, $service->canonicalReads);
        $this->assertSame(1, PmExecution::withTrashed()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
    }

    /**
     * S1-15: canonical collision tanpa winner authoritative tidak disamarkan.
     */
    public function test_s1_unresolvable_canonical_collision_rethrows_original_exception(): void
    {
        $violation = $this->makeUniqueViolation('pm_executions_pm_schedule_date_id_unique', 'pm_executions');
        $service = $this->collisionService($violation, true);

        try {
            $service->startExecutionOnly($this->s1Request(), $this->machine, $this->operator);
            $this->fail('Collision tanpa canonical harus melempar exception asli.');
        } catch (UniqueConstraintViolationException $caught) {
            $this->assertSame($violation, $caught);
        }

        $this->assertSame(0, PmExecution::withTrashed()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
    }

    /**
     * S1-16: fallback tetap menegakkan lifecycle waiting_review.
     */
    public function test_s1_canonical_collision_on_processed_occurrence_is_rejected_by_lifecycle(): void
    {
        PmExecution::query()->create([
            'pm_schedule_date_id' => $this->scheduleDate->id,
            'machine_id' => $this->machine->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'waiting_review',
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subMinutes(10),
        ]);
        $service = $this->collisionService($this->makeUniqueViolation('pm_executions_pm_schedule_date_id_unique', 'pm_executions'));

        try {
            $service->startExecutionOnly($this->s1Request(), $this->machine, $this->operator);
            $this->fail('waiting_review harus tetap ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('PM untuk occurrence ini sudah diproses dan tidak dapat dimulai ulang.', collect($e->errors())->flatten()->first());
        }

        $this->assertSame(1, PmExecution::withTrashed()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
    }

    /**
     * S1-17: HTTP upload memakai execution canonical dan part occurrence yang benar.
     */
    public function test_s1_media_upload_route_uses_same_canonical_execution_and_owned_part(): void
    {
        $started = app(PmExecutionService::class)->startExecutionOnly($this->s1Request(), $this->machine, $this->operator);
        $this->actingAs($this->operator)->post("/pm/executor/{$this->machine->machine_code}/media/upload", [
            'part_id' => $this->part->id,
            'part_note' => 'Foto via route produksi',
            'media_file' => UploadedFile::fake()->image('canonical.jpg', 640, 480),
        ])->assertRedirect("/pm/executor/{$this->machine->machine_code}");

        $media = PmExecutionMedia::query()->firstOrFail();
        $this->assertSame($started->id, (int) $media->pm_execution_id);
        $this->assertSame($this->part->id, (int) $media->pm_checksheet_part_id);
        $execution = PmExecution::query()->findOrFail($media->pm_execution_id);
        $execution->loadMissing('scheduleDate.schedule');
        $this->assertSame((int) $execution->scheduleDate->schedule->pm_checksheet_machine_id, (int) $this->part->pm_checksheet_machine_id);
        Storage::disk('public')->assertExists($media->file_path);
    }

    /**
     * S1-18: cross-occurrence part ditolak sebelum persistence file/row.
     */
    public function test_s1_media_upload_route_rejects_cross_occurrence_part_without_file_or_row(): void
    {
        $otherChecksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-CH-ROUTE-OTHER',
            'checksheet_name' => 'Checksheet Lain Route',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
        $otherChecksheetMachine = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $otherChecksheet->id,
            'machine_id' => $this->machine->id,
            'created_by' => $this->admin->id,
        ]);
        $otherPart = PmChecksheetPart::query()->create([
            'pm_checksheet_machine_id' => $otherChecksheetMachine->id,
            'part_name' => 'Part Route Occurrence Lain',
            'is_active' => true,
        ]);
        $filesBefore = Storage::disk('public')->files('pm-execution-media');

        $this->actingAs($this->operator)->postJson("/pm/executor/{$this->machine->machine_code}/media/upload", [
            'part_id' => $otherPart->id,
            'part_note' => 'Part salah occurrence',
            'media_file' => UploadedFile::fake()->image('cross.jpg', 640, 480),
        ])->assertStatus(422)->assertJsonPath('errors.part_id.0', 'Part media tidak sesuai dengan checksheet occurrence PM.');

        $this->assertSame(0, PmExecutionMedia::query()->count());
        $this->assertSame($filesBefore, Storage::disk('public')->files('pm-execution-media'));
    }

    /**
     * S1-19: part_id positif yang tidak ada tidak boleh berubah menjadi unassigned.
     */
    public function test_s1_media_upload_route_rejects_missing_part_instead_of_silently_unassigning(): void
    {
        $missingPartId = (int) PmChecksheetPart::query()->max('id') + 999;
        $filesBefore = Storage::disk('public')->files('pm-execution-media');

        $this->actingAs($this->operator)->postJson("/pm/executor/{$this->machine->machine_code}/media/upload", [
            'part_id' => $missingPartId,
            'part_note' => 'Part tidak ada',
            'media_file' => UploadedFile::fake()->image('missing.jpg', 640, 480),
        ])->assertStatus(422)->assertJsonPath('errors.part_id.0', 'Part media tidak sesuai dengan checksheet occurrence PM.');

        $this->assertSame(0, PmExecutionMedia::query()->count());
        $this->assertSame($filesBefore, Storage::disk('public')->files('pm-execution-media'));
    }

    /**
     * S2-01: migration membuat named unique index exact dan memertahankan
     * ordinary index serta NOT NULL pada pm_schedule_date_id.
     */
    public function test_s2_migration_creates_named_unique_index_and_preserves_existing_constraints(): void
    {
        $indexes = collect(Schema::getIndexes('pm_executions'))->keyBy('name');

        $this->assertTrue($indexes->has('pm_executions_pm_schedule_date_id_unique'));
        $this->assertTrue((bool) $indexes->get('pm_executions_pm_schedule_date_id_unique')['unique']);
        $this->assertTrue($indexes->has('pm_executions_pm_schedule_date_id_index'));
        $this->assertFalse((bool) $indexes->get('pm_executions_pm_schedule_date_id_index')['unique']);

        // Kolom canonical tetap NOT NULL; migration tidak mengubah perilaku nullability.
        $column = collect(Schema::getColumns('pm_executions'))->firstWhere('name', 'pm_schedule_date_id');
        $this->assertNotNull($column);
        $this->assertFalse((bool) $column['nullable']);
    }

    /**
     * S2-02: direct insert database kedua untuk occurrence yang sama ditolak
     * oleh unique index (bypass resolver aplikasi); count akhir tetap satu.
     */
    public function test_s2_second_direct_insert_for_same_occurrence_is_rejected_by_unique_index(): void
    {
        PmExecution::query()->create([
            'pm_schedule_date_id' => $this->scheduleDate->id,
            'machine_id' => $this->machine->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'in_progress',
            'started_at' => now()->subHour(),
        ]);

        try {
            DB::table('pm_executions')->insert([
                'pm_schedule_date_id' => $this->scheduleDate->id,
                'machine_id' => $this->machine->id,
                'operator_id' => $this->operator->id,
                'operator_name_snapshot' => $this->operator->name,
                'status' => 'in_progress',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Insert kedua harus ditolak oleh unique index.');
        } catch (UniqueConstraintViolationException $e) {
            $this->assertCanonicalUniqueViolation($e);
        }

        $this->assertSame(1, PmExecution::withTrashed()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
    }

    /**
     * S2-03: execution soft-deleted tetap menempati unique index, sehingga
     * direct insert pengganti untuk occurrence yang sama ditolak database.
     */
    public function test_s2_soft_deleted_execution_still_occupies_unique_index_and_blocks_direct_insert(): void
    {
        $execution = PmExecution::query()->create([
            'pm_schedule_date_id' => $this->scheduleDate->id,
            'machine_id' => $this->machine->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'in_progress',
            'started_at' => now()->subHour(),
        ]);
        $execution->delete(); // soft delete: row tetap ada dan menempati unique index

        try {
            DB::table('pm_executions')->insert([
                'pm_schedule_date_id' => $this->scheduleDate->id,
                'machine_id' => $this->machine->id,
                'operator_id' => $this->operator->id,
                'operator_name_snapshot' => $this->operator->name,
                'status' => 'in_progress',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Insert pengganti harus ditolak karena row soft-deleted tetap menempati unique index.');
        } catch (UniqueConstraintViolationException $e) {
            $this->assertCanonicalUniqueViolation($e);
        }

        $this->assertSame(1, PmExecution::withTrashed()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
        $this->assertSame(0, PmExecution::query()->where('pm_schedule_date_id', $this->scheduleDate->id)->count());
    }

    /**
     * S2: validasi pesan unique violation sesuai driver database test.
     * MySQL menyebut nama index; SQLite memakai pesan UNIQUE constraint failed.
     */
    private function assertCanonicalUniqueViolation(UniqueConstraintViolationException $e): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->assertStringContainsString('pm_executions_pm_schedule_date_id_unique', $e->getMessage());

            return;
        }

        $this->assertStringContainsString('UNIQUE constraint failed', $e->getMessage());
    }
}
