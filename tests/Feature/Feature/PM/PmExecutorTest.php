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
use App\Services\PM\PmExecutionMediaService;
use App\Services\PM\PmExecutionService;
use App\Services\PM\PmScheduleDateReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

    /**
     * Recreated TASK-003 Slice-5 regression: a stale resolved candidate must
     * be rejected before draft items or media are mutated.
     */
    public function test_stale_submit_is_rejected_before_any_draft_mutation(): void
    {
        $candidate = $this->createExecutionWithMedia('in_progress', [
            'started_at' => now()->subMinutes(5),
        ]);
        $itemsBefore = PmExecutionItem::query()->where('pm_execution_id', $candidate->id)->count();
        $mediaBefore = PmExecutionMedia::query()->where('pm_execution_id', $candidate->id)->count();

        // Simulasikan perubahan lifecycle setelah resolver mengembalikan kandidat stale.
        PmExecution::query()->whereKey($candidate->id)->update(['status' => 'waiting_review']);
        $staleCandidate = PmExecution::query()->findOrFail($candidate->id);
        $staleCandidate->status = 'in_progress';

        $service = \Mockery::mock(PmExecutionService::class)->makePartial();
        $service->shouldReceive('startExecutionOnly')->once()->andReturn($staleCandidate);
        $this->app->instance(PmExecutionService::class, $service);

        try {
            $service->startOrSaveDraft(
                request: Request::create('/'),
                machine: $this->machine,
                operator: $this->operator,
                actionValues: [$this->actionStandard->id => 'OK'],
                numberValues: [$this->numberStandard->id => '60', $this->rangeStandard->id => '120'],
                partNotes: [],
            );
            $this->fail('Stale submit harus ditolak sebelum mutation draft.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'PM sudah tidak dalam status aktif; submit ditolak.',
                collect($exception->errors())->flatten()->first(),
            );
        }

        $this->assertSame($itemsBefore, PmExecutionItem::query()->where('pm_execution_id', $candidate->id)->count());
        $this->assertSame($mediaBefore, PmExecutionMedia::query()->where('pm_execution_id', $candidate->id)->count());
        $this->assertDatabaseHas('pm_executions', ['id' => $candidate->id, 'status' => 'waiting_review']);
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
}
