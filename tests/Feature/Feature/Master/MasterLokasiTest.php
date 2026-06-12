<?php

namespace Tests\Feature\Feature\Master;

use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterLokasiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_admin_can_view_master_lokasi_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/pm/master-lokasi');

        $response->assertOk();
        $response->assertSee('Master Lokasi');
        $response->assertSee('Tambah Lokasi');
        $response->assertSee('Cari kode atau nama lokasi...');
    }

    public function test_only_admin_can_access_master_lokasi_page(): void
    {
        $guestSession = GuestSession::query()->create([
            'guest_name' => 'Guest PRIME',
            'session_id' => 'guest-session',
            'login_at' => now(),
        ]);

        $this->actingAs($this->operator)
            ->get('/pm/master-lokasi')
            ->assertRedirect('/403');

        $this->withSession([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ])->get('/pm/master-lokasi')->assertRedirect('/403');
    }

    public function test_admin_can_create_location_and_activity_is_logged(): void
    {
        $response = $this->actingAs($this->admin)->post('/pm/master-lokasi', [
            'location_code' => 'loc-new',
            'location_name' => 'Gedung Baru',
            'description' => 'Gudang ekspedisi baru',
            'is_active' => '1',
        ]);

        $response->assertRedirect('/pm/master-lokasi');
        $response->assertSessionHas('flash_success');

        $this->assertDatabaseHas('locations', [
            'location_code' => 'LOC-NEW',
            'location_name' => 'Gedung Baru',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('user_activity_logs', [
            'module_name' => 'master_lokasi',
            'action' => 'create',
            'actor_name_snapshot' => 'Admin PRIME',
        ]);
    }

    public function test_admin_can_update_location(): void
    {
        $location = Location::query()->create([
            'location_code' => 'LOC-01',
            'location_name' => 'Gedung A',
            'description' => 'Deskripsi awal',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put("/pm/master-lokasi/{$location->id}", [
            'location_code' => 'LOC-01A',
            'location_name' => 'Gedung A Utara',
            'description' => 'Deskripsi baru',
            'is_active' => '0',
        ]);

        $response->assertRedirect('/pm/master-lokasi');
        $response->assertSessionHas('flash_success');

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'location_code' => 'LOC-01A',
            'location_name' => 'Gedung A Utara',
            'description' => 'Deskripsi baru',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('user_activity_logs', [
            'module_name' => 'master_lokasi',
            'action' => 'update',
            'record_id' => $location->id,
        ]);
    }

    public function test_search_and_filter_status_work_on_location_listing(): void
    {
        Location::query()->create([
            'location_code' => 'LOC-ACT',
            'location_name' => 'Gedung Aktif',
            'is_active' => true,
        ]);

        Location::query()->create([
            'location_code' => 'LOC-OFF',
            'location_name' => 'Gedung Nonaktif',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->get('/pm/master-lokasi?search=OFF&status=inactive');

        $response->assertOk();
        $response->assertSee('LOC-OFF');
        $response->assertSee('Gedung Nonaktif');
        $response->assertDontSee('LOC-ACT');
    }

    public function test_location_listing_uses_ten_rows_per_page(): void
    {
        foreach (range(1, 11) as $index) {
            Location::query()->create([
                'location_code' => sprintf('LOC-%02d', $index),
                'location_name' => "Gedung {$index}",
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($this->admin)->get('/pm/master-lokasi');

        $response->assertOk();
        $response->assertSee('Menampilkan 1-10 dari total 11 data');
        $response->assertSee('Next');
    }

    public function test_admin_can_deactivate_and_activate_location(): void
    {
        $location = Location::query()->create([
            'location_code' => 'LOC-01',
            'location_name' => 'Gedung A',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->patch("/pm/master-lokasi/{$location->id}/nonaktifkan")
            ->assertRedirect('/pm/master-lokasi');

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'is_active' => false,
        ]);

        $this->actingAs($this->admin)
            ->patch("/pm/master-lokasi/{$location->id}/aktifkan")
            ->assertRedirect('/pm/master-lokasi');

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'is_active' => true,
        ]);

        $this->assertSame(
            2,
            UserActivityLog::query()
                ->where('module_name', 'master_lokasi')
                ->whereIn('action', ['deactivate', 'activate'])
                ->count(),
        );
    }

    public function test_admin_can_delete_unused_location(): void
    {
        $location = Location::query()->create([
            'location_code' => 'LOC-DEL',
            'location_name' => 'Gedung Hapus',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->delete("/pm/master-lokasi/{$location->id}");

        $response->assertRedirect('/pm/master-lokasi');
        $response->assertSessionHas('flash_success', 'Lokasi berhasil dihapus permanen.');
        $this->assertDatabaseMissing('locations', [
            'id' => $location->id,
        ]);
        $this->assertDatabaseHas('user_activity_logs', [
            'module_name' => 'master_lokasi',
            'action' => 'delete',
            'record_id' => $location->id,
        ]);
    }

    public function test_delete_is_blocked_when_location_is_still_used_by_active_machine(): void
    {
        $location = Location::query()->create([
            'location_code' => 'LOC-BLOCK',
            'location_name' => 'Gedung Aktif',
            'is_active' => true,
        ]);

        Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'MCH-001',
            'machine_name' => 'Air Compressor',
            'qr_token' => 'qr-air-compressor',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete("/pm/master-lokasi/{$location->id}");

        $response->assertRedirect('/pm/master-lokasi');
        $response->assertSessionHas('flash_error');
        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
        ]);
        $this->assertDatabaseMissing('user_activity_logs', [
            'module_name' => 'master_lokasi',
            'action' => 'delete',
            'record_id' => $location->id,
        ]);
    }
}
