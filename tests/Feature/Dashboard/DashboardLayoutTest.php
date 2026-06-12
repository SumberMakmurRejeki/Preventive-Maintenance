<?php

namespace Tests\Feature\Dashboard;

use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        $location = Location::query()->create([
            'location_code' => 'LOC-01',
            'location_name' => 'Area Produksi',
            'is_active' => true,
        ]);

        $this->machine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'MC-001',
            'machine_name' => 'Air Compressor G1',
            'qr_token' => 'qr-air-compressor-g1',
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
    }

    public function test_admin_dashboard_uses_grouped_sidebar_layout(): void
    {
        $response = $this->actingAs($this->admin)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('DASHBOARD PRIME');
        $response->assertSee('PM Management');
        $response->assertSee('Breakdown Management');
        $response->assertSee('Report');
        $response->assertSee('Kalender');
        $response->assertSee('Pengaturan User');
        $response->assertSee('data-testid="admin-notification-toggle"', false);
    }

    public function test_guest_dashboard_only_shows_guest_safe_navigation(): void
    {
        $guestSession = GuestSession::query()->create([
            'guest_name' => 'Guest PRIME',
            'session_id' => 'guest-prime-session',
            'login_at' => now(),
        ]);

        $response = $this
            ->withSession([
                'guest_session_id' => $guestSession->id,
                'guest_name' => $guestSession->guest_name,
            ])
            ->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('DASHBOARD PRIME');
        $response->assertSee('Guest PRIME');
        $response->assertDontSee('PM Management');
        $response->assertDontSee('Pengaturan User');
        $response->assertDontSee('data-testid="admin-notification-toggle"', false);
    }

    public function test_operator_machine_page_uses_compact_shell_without_admin_sidebar(): void
    {
        $response = $this->actingAs($this->operator)->get('/machines/MC-001');

        $response->assertStatus(200);
        $response->assertSee('Halaman Mesin PRIME');
        $response->assertSee('PM Executor');
        $response->assertDontSee('DASHBOARD PRIME');
        $response->assertDontSee('PM Management');
        $response->assertDontSee('data-testid="admin-notification-toggle"', false);
    }
}
