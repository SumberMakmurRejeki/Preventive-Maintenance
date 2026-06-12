<?php

namespace Tests\Feature\Feature\Master;

use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
}
