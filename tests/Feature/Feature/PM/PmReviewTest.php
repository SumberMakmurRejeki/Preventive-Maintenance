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
use App\Models\PmExecutionHistory;
use App\Models\PmExecutionItem;
use App\Models\PmExecutionMedia;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Models\PrimeNotification;
use App\Models\User;
use App\Services\PM\PmReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PmReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected Machine $machine;

    protected PmExecution $execution;

    protected PmExecutionItem $actionItem;

    protected PmExecutionItem $numberItem;

    protected PmScheduleDate $scheduleDate;

    protected PmChecksheetPart $part;

    protected PmChecksheetStandard $actionStandard;

    protected PmChecksheetStandard $numberStandard;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $location = Location::query()->create([
            'location_code' => 'LOC-01',
            'location_name' => 'Line 1',
            'is_active' => true,
        ]);

        $this->machine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'MC-01',
            'machine_name' => 'Filling Machine A',
            'qr_token' => 'qr-mc-01',
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
            'checksheet_code' => 'PM-CH-001',
            'checksheet_name' => 'Checksheet PM',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $checksheetMachine = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
            'assigned_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        $this->part = PmChecksheetPart::query()->create([
            'pm_checksheet_machine_id' => $checksheetMachine->id,
            'part_name' => 'Sistem Mekanikal',
            'is_active' => true,
        ]);

        $this->actionStandard = PmChecksheetStandard::query()->create([
            'pm_checksheet_part_id' => $this->part->id,
            'standard_name' => 'Kondisi Gearbox',
            'input_type' => 'action',
            'action_options' => ['OK', 'LUBRIKASI'],
            'is_required' => true,
            'is_active' => true,
        ]);

        $this->numberStandard = PmChecksheetStandard::query()->create([
            'pm_checksheet_part_id' => $this->part->id,
            'standard_name' => 'Suhu Bearing',
            'input_type' => 'number',
            'target_value' => 50,
            'unit' => 'C',
            'is_required' => true,
            'is_active' => true,
        ]);

        $schedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $checksheetMachine->id,
            'frequency_type' => 'daily',
            'start_date' => now()->subDay()->toDateString(),
            'generate_until' => now()->addDay()->toDateString(),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->scheduleDate = PmScheduleDate::query()->create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'waiting_review',
        ]);

        $this->execution = PmExecution::query()->create([
            'pm_schedule_date_id' => $this->scheduleDate->id,
            'machine_id' => $this->machine->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'waiting_review',
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subMinutes(20),
        ]);

        $this->actionItem = PmExecutionItem::query()->create([
            'pm_execution_id' => $this->execution->id,
            'pm_checksheet_part_id' => $this->part->id,
            'pm_checksheet_standard_id' => $this->actionStandard->id,
            'part_name_snapshot' => 'Sistem Mekanikal',
            'standard_name_snapshot' => 'Kondisi Gearbox',
            'input_type_snapshot' => 'action',
            'action_options_snapshot' => ['OK', 'LUBRIKASI'],
            'action_value' => 'OK',
            'is_warning' => false,
            'note' => 'Catatan lama',
        ]);

        $this->numberItem = PmExecutionItem::query()->create([
            'pm_execution_id' => $this->execution->id,
            'pm_checksheet_part_id' => $this->part->id,
            'pm_checksheet_standard_id' => $this->numberStandard->id,
            'part_name_snapshot' => 'Sistem Mekanikal',
            'standard_name_snapshot' => 'Suhu Bearing',
            'input_type_snapshot' => 'number',
            'target_value_snapshot' => 50,
            'unit_snapshot' => 'C',
            'number_value' => 50,
            'is_warning' => false,
            'note' => 'Catatan lama',
        ]);

        Storage::disk('public')->put('pm-execution-media/sample.jpg', 'file-content');
        PmExecutionMedia::query()->create([
            'pm_execution_id' => $this->execution->id,
            'pm_checksheet_part_id' => $this->part->id,
            'part_name_snapshot' => 'Sistem Mekanikal',
            'file_type' => 'photo',
            'file_path' => 'pm-execution-media/sample.jpg',
            'file_name' => 'sample.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1000,
            'original_file_size' => 1000,
            'compressed_file_size' => 1000,
            'uploaded_by' => $this->operator->id,
            'uploaded_by_name_snapshot' => $this->operator->name,
        ]);

        PrimeNotification::query()->create([
            'notification_type' => 'pm_waiting_review',
            'title' => 'Waiting review',
            'message' => 'PM waiting review',
            'target_role' => 'admin',
            'related_table' => 'pm_executions',
            'related_id' => $this->execution->id,
            'target_url' => '/pm/review',
        ]);
    }

    public function test_only_admin_can_access_pm_review_pages(): void
    {
        $this->actingAs($this->operator)->get('/pm/review')->assertRedirect('/403');

        $guestSession = GuestSession::query()->create([
            'guest_name' => 'Guest PRIME',
            'session_id' => 'guest-session',
            'login_at' => now(),
        ]);

        $this->withSession([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ])->get('/pm/review')->assertRedirect('/403');
    }

    public function test_admin_can_view_pm_review_list_and_detail(): void
    {
        $this->actingAs($this->admin)
            ->get('/pm/review')
            ->assertOk()
            ->assertSee('PM Review')
            ->assertSee('MC-01');

        $this->actingAs($this->admin)
            ->get("/pm/review/{$this->execution->id}")
            ->assertOk()
            ->assertSee('Kondisi Gearbox')
            ->assertSee('Suhu Bearing');
    }

    public function test_admin_can_update_pm_review_and_history_is_created(): void
    {
        $updatedSubmittedAt = now()->subMinutes(10)->seconds(0);

        $response = $this->actingAs($this->admin)->put("/pm/review/{$this->execution->id}", [
            'items' => [
                $this->actionItem->id => ['action_value' => 'LUBRIKASI'],
                $this->numberItem->id => ['number_value' => 45],
            ],
            'part_notes' => [
                $this->part->id => 'Catatan baru',
            ],
            'submitted_at' => $updatedSubmittedAt->format('Y-m-d\TH:i'),
            'change_note' => 'Koreksi input operator',
            'review_note' => 'Perlu dipantau minggu depan',
        ]);

        $response->assertRedirect("/pm/review/{$this->execution->id}");

        $this->assertDatabaseHas('pm_execution_items', [
            'id' => $this->actionItem->id,
            'action_value' => 'LUBRIKASI',
        ]);

        $this->assertDatabaseHas('pm_execution_items', [
            'id' => $this->numberItem->id,
            'number_value' => 45,
            'is_warning' => 1,
        ]);

        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'submitted_at' => $updatedSubmittedAt->format('Y-m-d H:i:00'),
        ]);

        $this->assertDatabaseHas('pm_execution_history', [
            'pm_execution_id' => $this->execution->id,
            'change_note' => 'Koreksi input operator',
        ]);

        $this->assertDatabaseHas('pm_execution_history', [
            'pm_execution_id' => $this->execution->id,
            'field_name' => 'Tanggal Submit PM',
            'new_value' => $updatedSubmittedAt->format('Y-m-d H:i:00'),
        ]);
    }

    public function test_machine_landing_uses_updated_submitted_at_after_pm_review_edit(): void
    {
        $updatedSubmittedAt = now()->subMinutes(5)->seconds(0);

        $this->actingAs($this->admin)->put("/pm/review/{$this->execution->id}", [
            'items' => [
                $this->actionItem->id => ['action_value' => 'OK'],
                $this->numberItem->id => ['number_value' => 50],
            ],
            'part_notes' => [
                $this->part->id => 'Catatan lama',
            ],
            'submitted_at' => $updatedSubmittedAt->format('Y-m-d\TH:i'),
            'change_note' => 'Koreksi tanggal submit PM',
            'review_note' => 'Tetap menunggu review',
        ])->assertRedirect("/pm/review/{$this->execution->id}");

        $this->actingAs($this->operator)
            ->get("/machines/{$this->machine->machine_code}")
            ->assertOk()
            ->assertSee($updatedSubmittedAt->translatedFormat('d M Y'));
    }

    public function test_admin_can_approve_pm_review(): void
    {
        $response = $this->actingAs($this->admin)->patch("/pm/review/{$this->execution->id}/approve", [
            'review_note' => 'Approve by admin',
        ]);

        $response->assertRedirect("/pm/review/{$this->execution->id}");

        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'related_table' => 'pm_executions',
            'related_id' => $this->execution->id,
        ]);
    }

    public function test_admin_cannot_delete_protected_execution_and_receives_business_message(): void
    {
        // Stale test updated: previous expectation was destructive delete.
        // Per ADR-003, protected executions (in_progress, waiting_review, approved) must be rejected.
        // setUp creates waiting_review execution, which is protected.
        $response = $this->actingAs($this->admin)->delete("/pm/review/{$this->execution->id}");

        // Rejection redirects back to detail with business-facing error
        $response->assertRedirect("/pm/review/{$this->execution->id}");
        $response->assertSessionHas('flash_error', 'Transaksi PM yang sudah dimulai tidak dapat dihapus karena merupakan data pekerjaan/histori.');

        // Execution, items, media, and schedule date all preserved
        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'waiting_review',
        ]);

        $this->assertDatabaseHas('pm_execution_items', [
            'pm_execution_id' => $this->execution->id,
        ]);

        $this->assertDatabaseHas('pm_execution_media', [
            'pm_execution_id' => $this->execution->id,
        ]);

        Storage::disk('public')->assertExists('pm-execution-media/sample.jpg');

        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'status' => 'waiting_review',
        ]);
    }

    public function test_admin_cannot_delete_in_progress_execution(): void
    {
        // Scenario A: in_progress execution delete protection
        $this->execution->update(['status' => 'in_progress']);
        $this->scheduleDate->update(['status' => 'in_progress']);

        // Create execution history to verify preservation
        $history = PmExecutionHistory::query()->create([
            'pm_execution_id' => $this->execution->id,
            'changed_by' => $this->operator->id,
            'changed_by_name_snapshot' => $this->operator->name,
            'field_name' => 'status',
            'old_value' => 'draft',
            'new_value' => 'in_progress',
            'change_note' => 'PM dimulai',
        ]);

        $initialItemCount = PmExecutionItem::query()->where('pm_execution_id', $this->execution->id)->count();
        $initialMediaCount = PmExecutionMedia::query()->where('pm_execution_id', $this->execution->id)->count();
        $initialHistoryCount = PmExecutionHistory::query()->where('pm_execution_id', $this->execution->id)->count();

        $response = $this->actingAs($this->admin)->delete("/pm/review/{$this->execution->id}");

        // Rejection with business-facing message
        $response->assertRedirect("/pm/review/{$this->execution->id}");
        $response->assertSessionHas('flash_error');

        // Zero mutation: execution still exists
        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'in_progress',
        ]);

        // Schedule date status preserved
        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'status' => 'in_progress',
        ]);

        // Items preserved
        $this->assertEquals($initialItemCount, PmExecutionItem::query()->where('pm_execution_id', $this->execution->id)->count());

        // Media preserved (both DB rows and files)
        $this->assertEquals($initialMediaCount, PmExecutionMedia::query()->where('pm_execution_id', $this->execution->id)->count());
        Storage::disk('public')->assertExists('pm-execution-media/sample.jpg');

        // History preserved
        $this->assertEquals($initialHistoryCount, PmExecutionHistory::query()->where('pm_execution_id', $this->execution->id)->count());
        $this->assertDatabaseHas('pm_execution_history', ['id' => $history->id]);
    }

    public function test_admin_cannot_delete_waiting_review_execution(): void
    {
        // Scenario B: waiting_review execution delete protection
        // setUp already creates waiting_review execution
        $initialItemCount = PmExecutionItem::query()->where('pm_execution_id', $this->execution->id)->count();
        $initialMediaCount = PmExecutionMedia::query()->where('pm_execution_id', $this->execution->id)->count();

        $response = $this->actingAs($this->admin)->delete("/pm/review/{$this->execution->id}");

        // Rejection with business-facing message
        $response->assertRedirect("/pm/review/{$this->execution->id}");
        $response->assertSessionHas('flash_error');

        // Zero mutation: execution still exists
        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'waiting_review',
        ]);

        // Schedule date status preserved
        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'status' => 'waiting_review',
        ]);

        // Items preserved
        $this->assertEquals($initialItemCount, PmExecutionItem::query()->where('pm_execution_id', $this->execution->id)->count());

        // Media preserved
        $this->assertEquals($initialMediaCount, PmExecutionMedia::query()->where('pm_execution_id', $this->execution->id)->count());
        Storage::disk('public')->assertExists('pm-execution-media/sample.jpg');
    }

    public function test_admin_cannot_delete_approved_execution(): void
    {
        // Scenario C: approved execution delete protection
        $this->execution->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'approved_by_name_snapshot' => $this->admin->name,
        ]);
        $this->scheduleDate->update(['status' => 'approved']);

        $initialItemCount = PmExecutionItem::query()->where('pm_execution_id', $this->execution->id)->count();
        $initialMediaCount = PmExecutionMedia::query()->where('pm_execution_id', $this->execution->id)->count();

        $response = $this->actingAs($this->admin)->delete("/pm/review/{$this->execution->id}");

        // Rejection with business-facing message
        $response->assertRedirect("/pm/review/{$this->execution->id}");
        $response->assertSessionHas('flash_error');

        // Zero mutation: execution still exists with approved metadata
        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
        ]);

        // Schedule date status preserved
        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'status' => 'approved',
        ]);

        // Items preserved
        $this->assertEquals($initialItemCount, PmExecutionItem::query()->where('pm_execution_id', $this->execution->id)->count());

        // Media preserved
        $this->assertEquals($initialMediaCount, PmExecutionMedia::query()->where('pm_execution_id', $this->execution->id)->count());
        Storage::disk('public')->assertExists('pm-execution-media/sample.jpg');
    }

    public function test_non_admin_cannot_delete_pm_execution(): void
    {
        // Scenario D: authorization regression
        $response = $this->actingAs($this->operator)->delete("/pm/review/{$this->execution->id}");

        // Authorization enforced
        $response->assertRedirect('/403');

        // Execution unchanged
        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'waiting_review',
        ]);
    }

    public function test_admin_can_still_view_and_approve_pm_review(): void
    {
        // Scenario E: valid PM Review workflow regression
        // View detail
        $this->actingAs($this->admin)
            ->get("/pm/review/{$this->execution->id}")
            ->assertOk()
            ->assertSee('Kondisi Gearbox');

        // Edit workflow still works
        $this->actingAs($this->admin)
            ->put("/pm/review/{$this->execution->id}", [
                'items' => [
                    $this->actionItem->id => ['action_value' => 'LUBRIKASI'],
                    $this->numberItem->id => ['number_value' => 55],
                ],
                'submitted_at' => now()->subMinutes(10)->format('Y-m-d\TH:i'),
                'change_note' => 'Koreksi review',
                'review_note' => 'Perlu dipantau',
            ])
            ->assertRedirect("/pm/review/{$this->execution->id}");

        $this->assertDatabaseHas('pm_execution_items', [
            'id' => $this->actionItem->id,
            'action_value' => 'LUBRIKASI',
        ]);

        // Approve workflow still works
        $this->actingAs($this->admin)
            ->patch("/pm/review/{$this->execution->id}/approve", [
                'review_note' => 'Approved',
            ])
            ->assertRedirect("/pm/review/{$this->execution->id}");

        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'approved',
        ]);
    }

    // ====================================================================
    // ADR-004 Slice 1 — Canonical Serialization + Lifecycle Contract
    // ====================================================================

    /**
     * S1-R1: Approval mentransisikan execution+occurrence ke approved dengan benar.
     * Regression test — harus tetap lulus setelah lock reorder.
     */
    public function test_s1_approval_transitions_execution_and_occurrence_to_approved(): void
    {
        $this->actingAs($this->admin)
            ->patch("/pm/review/{$this->execution->id}/approve", [
                'review_note' => 'Disetujui setelah canonical resolver.',
            ])
            ->assertRedirect("/pm/review/{$this->execution->id}");

        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'review_note' => 'Disetujui setelah canonical resolver.',
        ]);
        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'status' => 'approved',
        ]);
    }

    /**
     * S1-R2: Approval membaca ulang lifecycle di bawah Machine → Date → Execution locks.
     * Dibuktikan: jika status execution berubah jadi approved di DB sebelum approve dipanggil,
     * approve menolak dengan pesan yang tepat.
     */
    public function test_s1_approval_rereads_lifecycle_under_locks(): void
    {
        // Ubah status di DB menjadi approved sebelum approve dipanggil
        PmExecution::query()->whereKey($this->execution->id)->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'approved_by_name_snapshot' => $this->admin->name,
        ]);

        try {
            app(PmReviewService::class)->approve(
                request: $this->s1Request(),
                execution: $this->execution, // stale model, status masih waiting_review di memory
                admin: $this->admin,
                reviewNote: 'Double approve attempt',
            );
            $this->fail('Approve harus ditolak saat execution sudah approved.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(
                'approved',
                mb_strtolower(collect($e->errors())->flatten()->first()),
            );
        }
    }

    /**
     * S1-R3: Test-test ADR-003 protected delete tetap lulus setelah perubahan Slice 1.
     * Ini adalah regression meta-test — memanggil skenario yang sama.
     */
    public function test_s1_adr003_protected_delete_tests_still_pass(): void
    {
        // Delete pada execution waiting_review (setUp default) tetap ditolak
        $response = $this->actingAs($this->admin)->delete("/pm/review/{$this->execution->id}");
        $response->assertRedirect("/pm/review/{$this->execution->id}");
        $response->assertSessionHas('flash_error');

        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'waiting_review',
        ]);
    }

    /**
     * S1-R4: Execution dengan machine mismatch tidak boleh di-approve.
     * Parent chain harus valid sebelum status execution dimutasi.
     */
    public function test_s1_approval_rejects_execution_machine_mismatch_without_mutation(): void
    {
        $otherMachine = Machine::query()->create([
            'location_id' => $this->machine->location_id,
            'machine_code' => 'MC-02',
            'machine_name' => 'Filling Machine B',
            'qr_token' => 'qr-mc-02',
            'is_active' => true,
        ]);

        // Simulasikan data historis rusak: occurrence tetap milik machine asli,
        // tetapi execution menunjuk machine lain.
        PmExecution::query()->whereKey($this->execution->id)->update([
            'machine_id' => $otherMachine->id,
            'status' => 'waiting_review',
        ]);

        try {
            app(PmReviewService::class)->approve(
                request: $this->s1Request(),
                execution: $this->execution,
                admin: $this->admin,
                reviewNote: 'Mismatch harus ditolak',
            );
            $this->fail('Approve harus ditolak saat machine execution mismatch dengan occurrence.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(
                'tidak sesuai',
                mb_strtolower(collect($e->errors())->flatten()->first()),
            );
        }

        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'machine_id' => $otherMachine->id,
            'status' => 'waiting_review',
        ]);
        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'machine_id' => $this->machine->id,
            'status' => 'waiting_review',
        ]);
    }

    private function s1Request(): Request
    {
        $request = Request::create('/', 'GET');
        $request->setLaravelSession(app('session.store'));

        return $request;
    }
}
