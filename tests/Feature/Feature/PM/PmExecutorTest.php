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
use App\Models\PmExecutionMedia;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
}
