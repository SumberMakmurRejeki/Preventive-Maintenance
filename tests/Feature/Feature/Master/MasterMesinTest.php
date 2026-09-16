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

        $this->assertSame('active', $machine->lifecycle_status);

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
            // Payload lifecycle yang disuntikkan ke update biasa tidak boleh mengubah state.
            'is_active' => '0',
        ]);

        $response->assertRedirect('/pm/master-mesin');

        $machine->refresh();

        $this->assertSame('MSN-001', $machine->machine_code);
        $this->assertSame('qr-msn-001', $machine->qr_token);
        $this->assertSame($newLocation->id, $machine->location_id);
        $this->assertSame('Genset Updated', $machine->machine_name);

        $this->assertSame('active', $machine->lifecycle_status);
        $this->assertTrue($machine->is_active);
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
            'lifecycle_status' => 'inactive',
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
            ->patch("/pm/master-mesin/{$machine->id}/nonaktifkan", ['reason' => 'Inspeksi preventive'])
            ->assertRedirect('/pm/master-mesin');

        $this->assertDatabaseHas('machines', [
            'id' => $machine->id,
            'is_active' => false,
            'lifecycle_status' => 'inactive',
        ]);

        $this->actingAs($this->admin)
            ->patch("/pm/master-mesin/{$machine->id}/aktifkan", ['reason' => 'Inspeksi selesai'])
            ->assertRedirect('/pm/master-mesin');

        $this->assertDatabaseHas('machines', [
            'id' => $machine->id,
            'is_active' => true,
            'lifecycle_status' => 'active',
        ]);

        $this->actingAs($this->admin)
            ->delete("/pm/master-mesin/{$machine->id}")
            ->assertRedirect('/pm/master-mesin');

        $this->assertDatabaseMissing('machines', [
            'id' => $machine->id,
        ]);
        Storage::disk('public')->assertMissing('qr-codes/msn-001.svg');
    }

    /**
     * TASK-006 Slice C: transisi ilegal RETIRED -> ACTIVE harus ditolak pada
     * HTTP boundary sebagai error bisnis terkendali, bukan uncontrolled 500.
     */
    public function test_illegal_activation_of_retired_machine_returns_controlled_error(): void
    {
        $retired = $this->makeMachineRow('MSN-ILL-ACT', 'Mesin Pensiun Ilegal', $this->activeLocation->id, 'retired');

        $response = $this->actingAs($this->admin)
            ->patch("/pm/master-mesin/{$retired->id}/aktifkan", ['reason' => 'Coba aktivasi ulang mesin pensiun']);

        // Boundary mengembalikan admin ke halaman master mesin dengan error
        // terkendali, tanpa flash success palsu.
        $response->assertRedirect('/pm/master-mesin');
        $response->assertSessionHas('flash_error');
        $response->assertSessionMissing('flash_success');

        // State lifecycle tetap RETIRED: tidak ada aktivasi ordinary yang terjadi.
        $this->assertSame('retired', $retired->fresh()->lifecycle_status);
        $this->assertFalse((bool) $retired->fresh()->is_active);

        // Tidak boleh tercipta audit transisi sukses yang menyesatkan.
        $this->assertDatabaseMissing('user_activity_logs', [
            'module_name' => 'master_mesin',
            'action' => 'lifecycle_transition',
            'record_id' => $retired->id,
        ]);
    }

    /**
     * Semua aksi lifecycle harus menolak transisi ilegal dari state terminal
     * secara konsisten: nonaktifkan dan pensiunkan atas mesin RETIRED.
     */
    public function test_illegal_deactivate_and_retire_on_retired_machine_are_handled_consistently(): void
    {
        $retired = $this->makeMachineRow('MSN-ILL-ALL', 'Mesin Pensiun Semua Aksi', $this->activeLocation->id, 'retired');

        $deactivate = $this->actingAs($this->admin)
            ->patch("/pm/master-mesin/{$retired->id}/nonaktifkan", ['reason' => 'Coba nonaktifkan mesin pensiun']);
        $deactivate->assertRedirect('/pm/master-mesin');
        $deactivate->assertSessionHas('flash_error');

        $retire = $this->actingAs($this->admin)
            ->patch("/pm/master-mesin/{$retired->id}/pensiunkan", ['reason' => 'Coba pensiunkan ulang mesin pensiun']);
        $retire->assertRedirect('/pm/master-mesin');
        $retire->assertSessionHas('flash_error');

        $this->assertSame('retired', $retired->fresh()->lifecycle_status);
        $this->assertFalse((bool) $retired->fresh()->is_active);

        $this->assertDatabaseMissing('user_activity_logs', [
            'module_name' => 'master_mesin',
            'action' => 'lifecycle_transition',
            'record_id' => $retired->id,
        ]);
    }

    /**
     * Proteksi authorization dan validasi request pada HTTP boundary lifecycle
     * harus tetap utuh: operator ditolak 403 dan reason wajib tervalidasi.
     */
    public function test_lifecycle_actions_keep_authorization_and_reason_validation(): void
    {
        $machine = $this->makeMachineRow('MSN-ILL-GUARD', 'Mesin Proteksi Aksi', $this->activeLocation->id, 'active');

        // Authorization: operator tidak boleh memicu aksi lifecycle.
        $this->actingAs($this->operator)
            ->patch("/pm/master-mesin/{$machine->id}/aktifkan", ['reason' => 'Aksi operator'])
            ->assertRedirect('/403');

        // Request validation: reason wajib sebelum service lifecycle dipanggil.
        $this->actingAs($this->admin)
            ->patch("/pm/master-mesin/{$machine->id}/aktifkan", [])
            ->assertSessionHasErrors('reason');

        $this->assertSame('active', $machine->fresh()->lifecycle_status);
        $this->assertTrue((bool) $machine->fresh()->is_active);
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

    // =========================================================================
    // TASK-006 Slice C (Correction 5-7): filter lokasi/status dan status detail
    // =========================================================================

    /**
     * Filter status memakai nilai lifecycle kanonik yang sama dengan
     * atribut row, sehingga memilih satu status tidak menyembunyikan semua baris.
     */
    public function test_machine_filters_use_canonical_lifecycle_values(): void
    {
        $retired = $this->makeMachineRow('MSN-CAN-RET', 'Mesin Pensiun', $this->inactiveLocation->id, 'retired');
        $this->makeMachineRow('MSN-CAN-INACT', 'Mesin Nonaktif', $this->inactiveLocation->id, 'inactive');
        $this->makeMachineRow('MSN-CAN-ACT', 'Mesin Aktif', $this->activeLocation->id, 'active');

        // Halaman memakai nilai kanonik pada option filter dan pada row.
        $page = $this->actingAs($this->admin)->get('/pm/master-mesin');
        $page->assertOk();
        $page->assertSee('value="active"', false);
        $page->assertSee('value="inactive"', false);
        $page->assertSee('value="retired"', false);
        $page->assertDontSee('value="aktif"', false);
        $page->assertDontSee('value="nonaktif"', false);
        $page->assertSee('data-status="retired"', false);
        $page->assertSee('data-status="active"', false);

        // RETIRED hanya muncul untuk filter retired.
        $retiredFilter = $this->actingAs($this->admin)->get('/pm/master-mesin?status=retired');
        $retiredFilter->assertOk();
        $retiredFilter->assertSee($retired->machine_code);
        $retiredFilter->assertDontSee('MSN-CAN-INACT');
        $retiredFilter->assertDontSee('MSN-CAN-ACT');

        // INACTIVE hanya muncul untuk filter inactive.
        $inactiveFilter = $this->actingAs($this->admin)->get('/pm/master-mesin?status=inactive');
        $inactiveFilter->assertOk();
        $inactiveFilter->assertSee('MSN-CAN-INACT');
        $inactiveFilter->assertDontSee($retired->machine_code);
        $inactiveFilter->assertDontSee('MSN-CAN-ACT');
    }

    /**
     * Filter lokasi dan filter lifecycle harus dapat dipakai bersamaan tanpa
     * saling meniadakan.
     */
    public function test_location_and_lifecycle_filters_coexist(): void
    {
        $this->makeMachineRow('MSN-LOC-A-ACT', 'Mesin A Aktif', $this->activeLocation->id, 'active');
        $this->makeMachineRow('MSN-LOC-B-ACT', 'Mesin B Aktif', $this->inactiveLocation->id, 'active');
        $this->makeMachineRow('MSN-LOC-B-INA', 'Mesin B Nonaktif', $this->inactiveLocation->id, 'inactive');
        $this->makeMachineRow('MSN-LOC-B-RET', 'Mesin B Pensiun', $this->inactiveLocation->id, 'retired');

        // Lokasi saja: hanya mesin lokasi tersebut.
        $byLocation = $this->actingAs($this->admin)->get("/pm/master-mesin?location_id={$this->activeLocation->id}");
        $byLocation->assertOk();
        $byLocation->assertSee('MSN-LOC-A-ACT');
        $byLocation->assertDontSee('MSN-LOC-B-ACT');
        $byLocation->assertDontSee('MSN-LOC-B-INA');
        $byLocation->assertDontSee('MSN-LOC-B-RET');

        // Lokasi + lifecycle: irisan keduanya.
        $combined = $this->actingAs($this->admin)->get("/pm/master-mesin?location_id={$this->inactiveLocation->id}&status=active");
        $combined->assertOk();
        $combined->assertSee('MSN-LOC-B-ACT');
        $combined->assertDontSee('MSN-LOC-A-ACT');
        $combined->assertDontSee('MSN-LOC-B-INA');
        $combined->assertDontSee('MSN-LOC-B-RET');

        // Lokasi + lifecycle inactive.
        $inactiveOnly = $this->actingAs($this->admin)->get("/pm/master-mesin?location_id={$this->inactiveLocation->id}&status=inactive");
        $inactiveOnly->assertOk();
        $inactiveOnly->assertSee('MSN-LOC-B-INA');
        $inactiveOnly->assertDontSee('MSN-LOC-B-ACT');
        $inactiveOnly->assertDontSee('MSN-LOC-A-ACT');
    }

    /**
     * Scope lokasi harus tetap terjaga pada halaman pagination berikutnya.
     */
    public function test_location_filter_scope_survives_pagination(): void
    {
        for ($index = 1; $index <= 11; $index++) {
            $this->makeMachineRow(sprintf('MSN-PAGE-A-%02d', $index), "Mesin Paginasi {$index}", $this->activeLocation->id, 'active');
        }
        $this->makeMachineRow('MSN-PAGE-B-01', 'Mesin Lokasi Lain', $this->inactiveLocation->id, 'active');

        $firstPage = $this->actingAs($this->admin)->get("/pm/master-mesin?location_id={$this->activeLocation->id}");
        $firstPage->assertOk();
        $firstPage->assertSee('MSN-PAGE-A-01');
        $firstPage->assertDontSee('MSN-PAGE-B-01');

        $secondPage = $this->actingAs($this->admin)->get("/pm/master-mesin?location_id={$this->activeLocation->id}&page=2");
        $secondPage->assertOk();
        $secondPage->assertSee('MSN-PAGE-A-11');
        $secondPage->assertDontSee('MSN-PAGE-B-01');
    }

    /**
     * Detail mesin RETIRED harus tampil sebagai Pensiun, bukan Nonaktif, dan
     * aksi aktivasi ordinary tidak boleh tersedia untuk mesin pensiun.
     */
    public function test_retired_machine_detail_and_actions_are_distinguished(): void
    {
        $retired = $this->makeMachineRow('MSN-DET-RET', 'Mesin Pensiun Detail', $this->activeLocation->id, 'retired');
        $this->makeMachineRow('MSN-DET-INA', 'Mesin Nonaktif Detail', $this->activeLocation->id, 'inactive');
        $this->makeMachineRow('MSN-DET-ACT', 'Mesin Aktif Detail', $this->activeLocation->id, 'active');

        $response = $this->actingAs($this->admin)->get('/pm/master-mesin');
        $response->assertOk();

        // Tiga status dibedakan pada atribut detail modal.
        $response->assertSee('data-machine-status="Aktif"', false);
        $response->assertSee('data-machine-status="Nonaktif"', false);
        $response->assertSee('data-machine-status="Pensiun"', false);

        // Mesin pensiun tidak menawarkan aktivasi ordinary.
        $response->assertDontSee(route('master-mesin.activate', $retired->id), false);
        $response->assertDontSee(route('master-mesin.deactivate', $retired->id), false);
        $response->assertDontSee(route('master-mesin.retire', $retired->id), false);
    }

    /**
     * Buat satu mesin dengan lifecycle kanonik untuk uji filter/daftar.
     */
    private function makeMachineRow(string $code, string $name, int $locationId, string $lifecycleStatus): Machine
    {
        return Machine::query()->create([
            'location_id' => $locationId,
            'machine_code' => $code,
            'machine_name' => $name,
            'qr_token' => 'qr-'.strtolower($code),
            'is_active' => $lifecycleStatus === 'active',
            'lifecycle_status' => $lifecycleStatus,
        ]);
    }
}
