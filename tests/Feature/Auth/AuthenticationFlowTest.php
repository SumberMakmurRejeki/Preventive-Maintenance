<?php

namespace Tests\Feature\Auth;

use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected Machine $activeMachine;

    protected Machine $inactiveMachine;

    protected function setUp(): void
    {
        parent::setUp();

        $location = Location::query()->create([
            'location_code' => 'LOC-01',
            'location_name' => 'Line Produksi',
            'is_active' => true,
        ]);

        $this->activeMachine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'MCH-001',
            'machine_name' => 'CNC Milling',
            'qr_token' => 'qr-mch-001',
            'is_active' => true,
        ]);

        $this->inactiveMachine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'MCH-002',
            'machine_name' => 'Sealing Machine',
            'qr_token' => 'qr-mch-002',
            'is_active' => false,
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
    }

    public function test_admin_login_redirects_to_dashboard(): void
    {
        $response = $this->post('/login', [
            'username' => 'admin.prime',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_login_page_renders_staff_and_guest_access_options(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Selamat Datang Kembali');
        $response->assertSee('Staf PRIME');
        $response->assertSee('Tamu / Pengunjung');
        $response->assertSee('Masuk Ke Dasbor');
        $response->assertSee('Masuk Sebagai Tamu');
    }

    public function test_operator_login_redirects_to_machine_access(): void
    {
        $response = $this->post('/login', [
            'username' => 'operator.prime',
            'password' => 'password',
        ]);

        $response->assertRedirect('/machine-access');
        $this->assertAuthenticatedAs($this->operator);
        $this->assertNotNull(session('operator_session_started_at'));
    }

    public function test_guest_login_creates_guest_session_and_redirects_to_dashboard(): void
    {
        $response = $this->post('/guest-login', [
            'guest_name' => 'Visitor PRIME',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('guest_sessions', [
            'guest_name' => 'Visitor PRIME',
        ]);
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $inactiveUser = User::query()->create([
            'name' => 'Inactive User',
            'username' => 'inactive.prime',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'is_active' => false,
        ]);

        $response = $this->from('/login')->post('/login', [
            'username' => $inactiveUser->username,
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_qr_before_login_redirects_to_login_and_then_machine_page(): void
    {
        $response = $this->get('/qr/qr-mch-001');

        $response->assertRedirect('/login');

        $loginResponse = $this->post('/login', [
            'username' => 'operator.prime',
            'password' => 'password',
        ]);

        $loginResponse->assertRedirect('/machines/MCH-001');
    }

    public function test_qr_after_login_redirects_operator_directly_to_machine_page(): void
    {
        $this->actingAs($this->operator);

        $response = $this->get('/qr/qr-mch-001');

        $response->assertRedirect('/machines/MCH-001');
    }

    public function test_guest_scanning_qr_is_redirected_to_dashboard(): void
    {
        $guestSession = GuestSession::query()->create([
            'guest_name' => 'Visitor PRIME',
            'session_id' => 'guest-session',
            'login_at' => now(),
        ]);

        $this->withSession([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ]);

        $response = $this->get('/qr/qr-mch-001');

        $response->assertRedirect('/dashboard');
    }

    public function test_operator_can_access_machine_by_manual_code(): void
    {
        $this->actingAs($this->operator);

        $response = $this->post('/machine-access', [
            'machine_code' => 'mch-001',
        ]);

        $response->assertRedirect('/machines/MCH-001');
    }

    public function test_invalid_machine_code_returns_validation_feedback(): void
    {
        $this->actingAs($this->operator);

        $response = $this->from('/machine-access')->post('/machine-access', [
            'machine_code' => 'unknown',
        ]);

        $response->assertRedirect('/machine-access');
        $response->assertSessionHasErrors('machine_code');
    }

    public function test_operator_is_blocked_from_dashboard(): void
    {
        $this->actingAs($this->operator);

        $response = $this->get('/dashboard');

        $response->assertRedirect('/403');
    }

    public function test_admin_is_blocked_from_machine_access(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/machine-access');

        $response->assertRedirect('/403');
    }

    public function test_guest_is_blocked_from_machine_page(): void
    {
        $guestSession = GuestSession::query()->create([
            'guest_name' => 'Visitor PRIME',
            'session_id' => 'guest-session',
            'login_at' => now(),
        ]);

        $this->withSession([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ]);

        $response = $this->get('/machines/MCH-001');

        $response->assertRedirect('/403');
    }

    public function test_operator_session_expires_after_eight_hours(): void
    {
        $this->actingAs($this->operator)
            ->withSession([
                'operator_session_started_at' => now()->subHours(9)->toIso8601String(),
            ]);

        $response = $this->get('/machine-access');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
