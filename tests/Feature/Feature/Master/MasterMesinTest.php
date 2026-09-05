<?php

namespace Tests\Feature\Feature\Master;

use App\Models\Breakdown;
use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmExecution;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Models\User;
use App\Services\Master\MachineService;
use App\Services\Master\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MasterMesinTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected Location $activeLocation;

    protected Location $inactiveLocation;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

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

        $this->activeLocation = Location::query()->create([
            'location_code' => 'LOC-ACT',
            'location_name' => 'Gedung A',
            'is_active' => true,
        ]);

        $this->inactiveLocation = Location::query()->create([
            'location_code' => 'LOC-OFF',
            'location_name' => 'Gedung Nonaktif',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_view_master_mesin_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/pm/master-mesin');

        $response->assertOk();
        $response->assertSee('Master Mesin');
        $response->assertSee('Tambah Mesin');
    }

    public function test_only_admin_can_access_master_mesin_page(): void
    {
        $guestSession = GuestSession::query()->create([
            'guest_name' => 'Guest PRIME',
            'session_id' => 'guest-session',
            'login_at' => now(),
        ]);

        $this->actingAs($this->operator)
            ->get('/pm/master-mesin')
            ->assertRedirect('/403');

        $this->withSession([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ])->get('/pm/master-mesin')->assertRedirect('/403');
    }

    public function test_admin_can_create_machine_and_qr_file_is_generated(): void
    {
        $response = $this->actingAs($this->admin)->post('/pm/master-mesin', [
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'msn-001',
            'machine_name' => 'Genset Utama A',
            'description' => 'Mesin backup listrik',
            'is_active' => '1',
        ]);

        $response->assertRedirect('/pm/master-mesin');
        $response->assertSessionHas('flash_success');

        $machine = Machine::query()->where('machine_code', 'MSN-001')->firstOrFail();

        $this->assertNotNull($machine->qr_token);
        $this->assertNotNull($machine->qr_code_path);
        Storage::disk('public')->assertExists($machine->qr_code_path);

        $this->assertDatabaseHas('user_activity_logs', [
            'module_name' => 'master_mesin',
            'action' => 'create',
            'record_id' => $machine->id,
        ]);
    }

    public function test_machine_update_keeps_machine_code_and_qr_token_stable_when_location_changes(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-001',
            'machine_name' => 'Genset Utama A',
            'qr_token' => 'qr-msn-001',
            'qr_code_path' => 'qr-codes/msn-001.svg',
            'is_active' => true,
        ]);

        $newLocation = Location::query()->create([
            'location_code' => 'LOC-B',
            'location_name' => 'Gedung B',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put("/pm/master-mesin/{$machine->id}", [
            'location_id' => $newLocation->id,
            'machine_code' => 'MSN-001',
            'machine_name' => 'Genset Updated',
            'description' => 'Deskripsi baru',
            'is_active' => '1',
        ]);

        $response->assertRedirect('/pm/master-mesin');

        $machine->refresh();

        $this->assertSame('MSN-001', $machine->machine_code);
        $this->assertSame('qr-msn-001', $machine->qr_token);
        $this->assertSame($newLocation->id, $machine->location_id);
        $this->assertSame('Genset Updated', $machine->machine_name);
    }

    public function test_machine_code_cannot_be_changed_after_creation(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-001',
            'machine_name' => 'Genset Utama A',
            'qr_token' => 'qr-msn-001',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->from('/pm/master-mesin')
            ->put("/pm/master-mesin/{$machine->id}", [
                'location_id' => $this->activeLocation->id,
                'machine_code' => 'MSN-999',
                'machine_name' => 'Genset Utama A',
                'description' => 'Percobaan ubah kode',
                'is_active' => '1',
            ]);

        $response->assertRedirect('/pm/master-mesin');
        $response->assertSessionHasErrors('machine_code');

        $machine->refresh();

        $this->assertSame('MSN-001', $machine->machine_code);
    }

    public function test_search_and_filters_work_for_machine_listing(): void
    {
        Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-001',
            'machine_name' => 'Genset A',
            'qr_token' => 'qr-msn-001',
            'is_active' => true,
        ]);

        $secondLocation = Location::query()->create([
            'location_code' => 'LOC-B',
            'location_name' => 'Gedung B',
            'is_active' => true,
        ]);

        Machine::query()->create([
            'location_id' => $secondLocation->id,
            'machine_code' => 'MSN-002',
            'machine_name' => 'Forklift B',
            'qr_token' => 'qr-msn-002',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->get("/pm/master-mesin?search=Forklift&location_id={$secondLocation->id}&status=inactive");

        $response->assertOk();
        $response->assertSee('MSN-002');
        $response->assertDontSee('MSN-001');
    }

    public function test_machine_listing_uses_ten_rows_per_page(): void
    {
        foreach (range(1, 11) as $index) {
            Machine::query()->create([
                'location_id' => $this->activeLocation->id,
                'machine_code' => sprintf('MSN-%03d', $index),
                'machine_name' => "Mesin {$index}",
                'qr_token' => "qr-msn-{$index}",
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($this->admin)->get('/pm/master-mesin');

        $response->assertOk();
        $response->assertSee('Menampilkan 1-10 dari total 11 data');
    }

    public function test_generate_qr_route_creates_qr_file_and_logs_activity(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-001',
            'machine_name' => 'Genset Utama A',
            'qr_token' => 'qr-msn-001',
            'qr_code_path' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post("/pm/master-mesin/{$machine->id}/generate-qr");

        $response->assertRedirect();
        $machine->refresh();

        $this->assertSame('qr-codes/msn-001.svg', $machine->qr_code_path);
        Storage::disk('public')->assertExists($machine->qr_code_path);
        $this->assertDatabaseHas('user_activity_logs', [
            'module_name' => 'master_mesin',
            'action' => 'generate_qr',
            'record_id' => $machine->id,
        ]);
    }

    public function test_admin_can_view_machine_detail_modal_payload_on_index_page(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-001',
            'machine_name' => 'Genset Utama A',
            'qr_token' => 'qr-msn-001',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/pm/master-mesin');

        $response->assertOk();
        $response->assertSee('data-machine-detail-open', false);
        $response->assertSee('machine-detail-modal', false);
        $response->assertSee('Genset Utama A');
        $response->assertSee('Lokasi');
    }

    public function test_detail_route_is_no_longer_accessible(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-001',
            'machine_name' => 'Genset Utama A',
            'qr_token' => 'qr-msn-001',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get("/pm/master-mesin/{$machine->id}");

        $response->assertStatus(405);
    }

    public function test_qr_link_redirects_operator_to_machine_page(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-001',
            'machine_name' => 'Genset Utama A',
            'qr_token' => 'qr-msn-001',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->operator)->get("/qr/{$machine->qr_token}");

        $response->assertRedirect("/machines/{$machine->machine_code}");
    }

    public function test_inactive_machine_page_shows_transactions_are_disabled_message(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-001',
            'machine_name' => 'Genset Utama A',
            'qr_token' => 'qr-msn-001',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->operator)->get("/machines/{$machine->machine_code}");

        $response->assertOk();
        $response->assertSee('Mesin nonaktif tetap bisa dilihat, tetapi transaksi baru harus disabled.');
    }

    public function test_admin_can_deactivate_activate_and_delete_machine(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-001',
            'machine_name' => 'Genset Utama A',
            'qr_token' => 'qr-msn-001',
            'qr_code_path' => 'qr-codes/msn-001.svg',
            'is_active' => true,
        ]);

        Storage::disk('public')->put('qr-codes/msn-001.svg', '<svg></svg>');

        $this->actingAs($this->admin)
            ->patch("/pm/master-mesin/{$machine->id}/nonaktifkan")
            ->assertRedirect('/pm/master-mesin');

        $this->assertDatabaseHas('machines', [
            'id' => $machine->id,
            'is_active' => false,
        ]);

        $this->actingAs($this->admin)
            ->patch("/pm/master-mesin/{$machine->id}/aktifkan")
            ->assertRedirect('/pm/master-mesin');

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin');

        $this->assertDatabaseMissing('machines', [
            'id' => $machine->id,
        ]);
        Storage::disk('public')->assertMissing('qr-codes/msn-001.svg');
    }

    public function test_inactive_location_cannot_be_used_when_creating_machine(): void
    {
        $response = $this->actingAs($this->admin)->from('/pm/master-mesin')->post('/pm/master-mesin', [
            'location_id' => $this->inactiveLocation->id,
            'machine_code' => 'MSN-001',
            'machine_name' => 'Mesin Salah Lokasi',
            'is_active' => '1',
        ]);

        $response->assertRedirect('/pm/master-mesin');
        $response->assertSessionHasErrors('location_id');
        $this->assertDatabaseMissing('machines', [
            'machine_code' => 'MSN-001',
        ]);
    }

    // ========================================================================
    // TASK-003 Slice 3 - Machine Historical Delete Protection
    // ========================================================================

    /**
     * A. Protected Machine - Breakdown exists (including soft-deleted)
     */
    public function test_machine_with_breakdown_cannot_be_deleted(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-BRK-001',
            'machine_name' => 'Mesin Dengan Breakdown',
            'qr_token' => 'qr-msn-brk-001',
            'qr_code_path' => 'qr-codes/msn-brk-001.svg',
            'is_active' => true,
        ]);

        Storage::disk('public')->put('qr-codes/msn-brk-001.svg', '<svg></svg>');

        // Create breakdown (active)
        Breakdown::query()->create([
            'breakdown_code' => 'BD-001',
            'machine_id' => $machine->id,
            'machine_name_snapshot' => $machine->machine_name,
            'location_name_snapshot' => $this->activeLocation->location_name,
            'problem' => 'Mesin rusak',
            'status' => 'open',
            'breakdown_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_error', 'Mesin yang sudah memiliki transaksi atau riwayat maintenance tidak dapat dihapus.');

        // Machine and QR must remain
        $this->assertDatabaseHas('machines', ['id' => $machine->id]);
        Storage::disk('public')->assertExists('qr-codes/msn-brk-001.svg');
    }

    public function test_machine_with_soft_deleted_breakdown_cannot_be_deleted(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-BRK-002',
            'machine_name' => 'Mesin Dengan Breakdown Soft-Deleted',
            'qr_token' => 'qr-msn-brk-002',
            'qr_code_path' => 'qr-codes/msn-brk-002.svg',
            'is_active' => true,
        ]);

        Storage::disk('public')->put('qr-codes/msn-brk-002.svg', '<svg></svg>');

        $breakdown = Breakdown::query()->create([
            'breakdown_code' => 'BD-002',
            'machine_id' => $machine->id,
            'machine_name_snapshot' => $machine->machine_name,
            'location_name_snapshot' => $this->activeLocation->location_name,
            'problem' => 'Mesin rusak',
            'status' => 'closed',
            'breakdown_at' => now(),
            'created_by' => $this->admin->id,
        ]);
        $breakdown->delete(); // soft delete

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_error', 'Mesin yang sudah memiliki transaksi atau riwayat maintenance tidak dapat dihapus.');

        $this->assertDatabaseHas('machines', ['id' => $machine->id]);
        Storage::disk('public')->assertExists('qr-codes/msn-brk-002.svg');
    }

    /**
     * B. Protected Machine - PM Execution exists (including soft-deleted)
     */
    public function test_machine_with_pm_execution_cannot_be_deleted(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-EXE-001',
            'machine_name' => 'Mesin Dengan Execution',
            'qr_token' => 'qr-msn-exe-001',
            'qr_code_path' => 'qr-codes/msn-exe-001.svg',
            'is_active' => true,
        ]);

        Storage::disk('public')->put('qr-codes/msn-exe-001.svg', '<svg></svg>');

        // Create schedule date first (required FK for execution)
        $scheduleDate = $this->createScheduleDateForMachine($machine);

        PmExecution::query()->create([
            'pm_schedule_date_id' => $scheduleDate->id,
            'machine_id' => $machine->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'approved',
            'started_at' => now(),
            'submitted_at' => now(),
            'approved_at' => now(),
            'operator_id' => $this->operator->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_error', 'Mesin yang sudah memiliki transaksi atau riwayat maintenance tidak dapat dihapus.');

        $this->assertDatabaseHas('machines', ['id' => $machine->id]);
        Storage::disk('public')->assertExists('qr-codes/msn-exe-001.svg');
    }

    public function test_machine_with_soft_deleted_pm_execution_cannot_be_deleted(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-EXE-002',
            'machine_name' => 'Mesin Dengan Execution Soft-Deleted',
            'qr_token' => 'qr-msn-exe-002',
            'qr_code_path' => 'qr-codes/msn-exe-002.svg',
            'is_active' => true,
        ]);

        Storage::disk('public')->put('qr-codes/msn-exe-002.svg', '<svg></svg>');

        $scheduleDate = $this->createScheduleDateForMachine($machine);

        $execution = PmExecution::query()->create([
            'pm_schedule_date_id' => $scheduleDate->id,
            'machine_id' => $machine->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'approved',
            'started_at' => now(),
            'submitted_at' => now(),
            'approved_at' => now(),
            'operator_id' => $this->operator->id,
        ]);
        $execution->delete(); // soft delete

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_error', 'Mesin yang sudah memiliki transaksi atau riwayat maintenance tidak dapat dihapus.');

        $this->assertDatabaseHas('machines', ['id' => $machine->id]);
    }

    /**
     * C. Protected Machine - Protected schedule occurrence (in_progress, waiting_review, approved)
     */
    public function test_machine_with_in_progress_occurrence_cannot_be_deleted(): void
    {
        $machine = $this->createMachineWithSchedule('MSN-OCC-001', 'in_progress');

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_error', 'Mesin yang sudah memiliki transaksi atau riwayat maintenance tidak dapat dihapus.');

        $this->assertDatabaseHas('machines', ['id' => $machine->id]);
    }

    public function test_machine_with_waiting_review_occurrence_cannot_be_deleted(): void
    {
        $machine = $this->createMachineWithSchedule('MSN-OCC-002', 'waiting_review');

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_error', 'Mesin yang sudah memiliki transaksi atau riwayat maintenance tidak dapat dihapus.');

        $this->assertDatabaseHas('machines', ['id' => $machine->id]);
    }

    public function test_machine_with_approved_occurrence_cannot_be_deleted(): void
    {
        $machine = $this->createMachineWithSchedule('MSN-OCC-003', 'approved');

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_error', 'Mesin yang sudah memiliki transaksi atau riwayat maintenance tidak dapat dihapus.');

        $this->assertDatabaseHas('machines', ['id' => $machine->id]);
    }

    /**
     * D. Protected Machine - Occurrence with execution/history descendant
     */
    public function test_machine_with_occurrence_having_execution_cannot_be_deleted(): void
    {
        $machine = $this->createMachineWithSchedule('MSN-OCC-004', 'scheduled');

        // Add execution to the occurrence
        $scheduleDate = PmScheduleDate::query()
            ->where('machine_id', $machine->id)
            ->firstOrFail();

        PmExecution::query()->create([
            'pm_schedule_date_id' => $scheduleDate->id,
            'machine_id' => $machine->id,
            'status' => 'approved',
            'started_at' => now(),
            'submitted_at' => now(),
            'approved_at' => now(),
            'operator_id' => $this->operator->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_error', 'Mesin yang sudah memiliki transaksi atau riwayat maintenance tidak dapat dihapus.');

        $this->assertDatabaseHas('machines', ['id' => $machine->id]);
    }

    /**
     * E. Protected Machine - Soft-deleted protected evidence
     */
    public function test_machine_with_soft_deleted_protected_occurrence_cannot_be_deleted(): void
    {
        $machine = $this->createMachineWithSchedule('MSN-OCC-005', 'approved');

        // Soft-delete the occurrence
        $scheduleDate = PmScheduleDate::query()
            ->where('machine_id', $machine->id)
            ->firstOrFail();
        $scheduleDate->delete();

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_error', 'Mesin yang sudah memiliki transaksi atau riwayat maintenance tidak dapat dihapus.');

        $this->assertDatabaseHas('machines', ['id' => $machine->id]);
    }

    /**
     * F. Mutable occurrence (scheduled/overdue/missed without execution) - NOT protected
     */
    public function test_machine_with_only_mutable_occurrence_can_be_deleted(): void
    {
        $machine = $this->createMachineWithSchedule('MSN-MUT-001', 'scheduled');

        // Verify no execution exists
        $this->assertDatabaseMissing('pm_executions', ['machine_id' => $machine->id]);

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_success');

        $this->assertDatabaseMissing('machines', ['id' => $machine->id]);
    }

    public function test_machine_with_only_overdue_occurrence_can_be_deleted(): void
    {
        $machine = $this->createMachineWithSchedule('MSN-MUT-002', 'overdue');

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_success');

        $this->assertDatabaseMissing('machines', ['id' => $machine->id]);
    }

    public function test_machine_with_only_missed_occurrence_can_be_deleted(): void
    {
        $machine = $this->createMachineWithSchedule('MSN-MUT-003', 'missed');

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_success');

        $this->assertDatabaseMissing('machines', ['id' => $machine->id]);
    }

    /**
     * G. Configuration-only Machine (assignment/parts/standards/schedule but no protected history) - NOT protected
     */
    public function test_configuration_only_machine_can_be_deleted(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-CONF-001',
            'machine_name' => 'Mesin Konfigurasi Saja',
            'qr_token' => 'qr-msn-conf-001',
            'qr_code_path' => 'qr-codes/msn-conf-001.svg',
            'is_active' => true,
        ]);

        Storage::disk('public')->put('qr-codes/msn-conf-001.svg', '<svg></svg>');

        // Create schedule but no execution or protected occurrence
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'CHK-001',
            'checksheet_name' => 'Checksheet Test',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $machine->id,
            'assigned_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'daily',
            'operational_from' => now()->subDays(30),
            'start_date' => now()->subDays(30),
            'generate_until' => now()->addDays(30),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_success');

        $this->assertDatabaseMissing('machines', ['id' => $machine->id]);
        Storage::disk('public')->assertMissing('qr-codes/msn-conf-001.svg');
    }

    /**
     * H. Repeated rejection - both reject safely, no accumulated mutation
     */
    public function test_repeated_delete_attempts_both_reject_safely(): void
    {
        $machine = $this->createMachineWithSchedule('MSN-REP-001', 'approved');

        // First attempt
        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_error');

        // Second attempt
        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_error');

        // Machine must still exist
        $this->assertDatabaseHas('machines', ['id' => $machine->id]);
    }

    /**
     * I. Authorization regression - existing admin-only behavior unchanged
     */
    public function test_operator_cannot_delete_machine(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-AUTH-001',
            'machine_name' => 'Mesin Auth Test',
            'qr_token' => 'qr-msn-auth-001',
            'qr_code_path' => 'qr-codes/msn-auth-001.svg',
            'is_active' => true,
        ]);

        $this->actingAs($this->operator)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('machines', ['id' => $machine->id]);
    }

    /**
     * J. QR ordering - QR remains on protected, cleanup on successful delete
     */
    public function test_qr_remains_when_machine_is_protected(): void
    {
        $machine = $this->createMachineWithSchedule('MSN-QR-001', 'approved');

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_error');

        // QR file must remain
        Storage::disk('public')->assertExists($machine->qr_code_path);
    }

    public function test_qr_cleaned_up_on_successful_delete(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-QR-002',
            'machine_name' => 'Mesin QR Cleanup',
            'qr_token' => 'qr-msn-qr-002',
            'qr_code_path' => 'qr-codes/msn-qr-002.svg',
            'is_active' => true,
        ]);

        Storage::disk('public')->put('qr-codes/msn-qr-002.svg', '<svg></svg>');

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin')
            ->assertSessionHas('flash_success');

        Storage::disk('public')->assertMissing('qr-codes/msn-qr-002.svg');
    }

    /**
     * Regression: false dari Storage::delete() harus menjadi kegagalan cleanup
     * yang dicatat setelah penghapusan Machine berhasil commit.
     */
    public function test_qr_cleanup_failure_from_false_delete_is_logged_after_machine_commit(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-QR-FALSE-001',
            'machine_name' => 'Mesin QR False Return',
            'qr_token' => 'qr-msn-qr-false-001',
            'qr_code_path' => 'qr-codes/msn-qr-false-001.svg',
            'is_active' => true,
        ]);

        $qrPath = 'qr-codes/msn-qr-false-001.svg';
        $diskMock = \Mockery::mock();
        $diskMock->shouldReceive('delete')
            ->once()
            ->with($qrPath)
            ->andReturnFalse();

        // Paksa filesystem mengembalikan false agar jalur kegagalan nyata teruji.
        Storage::shouldReceive('disk')
            ->once()
            ->with('public')
            ->andReturn($diskMock);
        Log::spy();

        $request = Request::create("/pm/master-mesin/{$machine->id}", 'DELETE');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $this->admin);

        $result = $this->app->make(MachineService::class)->delete($request, $machine);

        // Database tetap committed; cleanup filesystem tidak boleh menghidupkan Machine.
        $this->assertTrue($result);
        $this->assertModelMissing($machine);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'QR cleanup failed after machine deletion',
                \Mockery::on(fn (array $context): bool => $context['machine_id'] === $machine->id
                    && $context['qr_path'] === $qrPath
                    && str_contains($context['error'], 'QR code asset')),
            );
    }

    /**
     * Path QR null atau kosong tetap menjadi no-op tanpa mengakses filesystem.
     */
    public function test_qr_cleanup_ignores_null_and_empty_path(): void
    {
        Storage::shouldReceive('disk')->never();

        $qrService = $this->app->make(QrCodeService::class);

        $qrService->deletePath(null);
        $qrService->deletePath('');

        $this->addToAssertionCount(1);
    }


    /**
     * K. Regression: QR cleanup tidak dipanggil ketika database transaction gagal
     *
     * Test ini membuktikan ordering contract: QR cleanup hanya dilakukan SETELAH
     * database transaction berhasil commit. Jika transaction gagal/rollback,
     * QR cleanup tidak boleh dipanggil untuk menghindari inkonsistensi database-filesystem.
     */
    public function test_qr_cleanup_not_invoked_when_database_transaction_fails(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-QR-FAIL-001',
            'machine_name' => 'Mesin QR Fail Test',
            'qr_token' => 'qr-msn-qr-fail-001',
            'qr_code_path' => 'qr-codes/msn-qr-fail-001.svg',
            'is_active' => true,
        ]);

        Storage::disk('public')->put('qr-codes/msn-qr-fail-001.svg', '<svg></svg>');

        // Mock QrCodeService untuk memastikan cleanup path-oriented tidak dipanggil.
        $qrServiceMock = \Mockery::mock(QrCodeService::class);
        $qrServiceMock->shouldNotReceive('deletePath');

        // Bind mock ke container
        $this->app->instance(QrCodeService::class, $qrServiceMock);

        // Force database transaction failure dengan menggunakan DB::transaction dan throw exception
        // setelah forceDelete dimulai tetapi sebelum commit
        DB::listen(function ($query) {
            if (str_contains($query->sql, 'delete from "machines"') || str_contains($query->sql, 'DELETE FROM `machines`')) {
                // Throw exception setelah DELETE query dimulai untuk simulate transaction failure
                throw new \Exception('Simulated database transaction failure');
            }
        });

        // Attempt delete - harus gagal karena exception
        try {
            $this->actingAs($this->admin)
                ->delete("/pm/master-mesin/{$machine->id}");
        } catch (\Exception $e) {
            // Expected exception
            $this->assertStringContainsString('Simulated database transaction failure', $e->getMessage());
        }

        // Machine harus masih ada karena transaction rollback
        $this->assertDatabaseHas('machines', ['id' => $machine->id]);

        // QR file harus masih ada karena cleanup tidak dipanggil
        Storage::disk('public')->assertExists('qr-codes/msn-qr-fail-001.svg');

        // Mockery akan memverifikasi bahwa deletePath tidak pernah dipanggil.
    }

    /**
     * Memastikan cleanup memakai path dari row Machine yang dikunci, bukan model stale.
     */
    public function test_successful_delete_cleans_authoritative_qr_path(): void
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => 'MSN-QR-AUTH-001',
            'machine_name' => 'Mesin QR Authoritative',
            'qr_token' => 'qr-msn-qr-auth-001',
            'qr_code_path' => 'qr-codes/current.svg',
            'is_active' => true,
        ]);

        $staleMachine = $machine->replicate();
        $staleMachine->id = $machine->id;
        $staleMachine->qr_code_path = 'qr-codes/old.svg';

        // Mock path-oriented cleanup agar path authoritative dapat diverifikasi langsung.
        $qrServiceMock = \Mockery::mock(QrCodeService::class);
        $qrServiceMock->shouldReceive('deletePath')
            ->once()
            ->with('qr-codes/current.svg');
        $qrServiceMock->shouldNotReceive('deleteForMachine');
        $this->app->instance(QrCodeService::class, $qrServiceMock);
        $request = Request::create("/pm/master-mesin/{$machine->id}", 'DELETE');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $this->admin);

        $result = $this->app->make(MachineService::class)->delete($request, $staleMachine);

        $this->assertTrue($result);
        $this->assertModelMissing($machine);
    }

    // ========================================================================
    // Helper methods
    // ========================================================================

    /**
     * Create a machine with a schedule and occurrence in the given status.
     */
    private function createMachineWithSchedule(string $machineCode, string $occurrenceStatus): Machine
    {
        $machine = Machine::query()->create([
            'location_id' => $this->activeLocation->id,
            'machine_code' => $machineCode,
            'machine_name' => "Mesin {$machineCode}",
            'qr_token' => "qr-{$machineCode}",
            'qr_code_path' => "qr-codes/{$machineCode}.svg",
            'is_active' => true,
        ]);

        Storage::disk('public')->put("qr-codes/{$machineCode}.svg", '<svg></svg>');

        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => "CHK-{$machineCode}",
            'checksheet_name' => "Checksheet {$machineCode}",
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $machine->id,
            'assigned_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        $schedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'daily',
            'operational_from' => now()->subDays(30),
            'start_date' => now()->subDays(30),
            'generate_until' => now()->addDays(30),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        PmScheduleDate::query()->create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $machine->id,
            'scheduled_date' => now()->subDay(),
            'status' => $occurrenceStatus,
            'generated_at' => now(),
        ]);

        return $machine;
    }

    /**
     * Helper: Create a schedule date for machine (used by execution tests).
     */
    private function createScheduleDateForMachine(Machine $machine): PmScheduleDate
    {
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'CS-HELPER-'.$machine->machine_code,
            'checksheet_name' => 'Helper Checksheet',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $machine->id,
            'assigned_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        $schedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'daily',
            'operational_from' => now()->subDays(30),
            'start_date' => now()->subDays(30),
            'generate_until' => now()->addDays(30),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        return PmScheduleDate::query()->create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $machine->id,
            'scheduled_date' => now()->subDay(),
            'status' => 'scheduled',
            'generated_at' => now(),
        ]);
    }
}
