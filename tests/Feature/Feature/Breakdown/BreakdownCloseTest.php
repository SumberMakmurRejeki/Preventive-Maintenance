<?php

namespace Tests\Feature\Feature\Breakdown;

use App\Models\Breakdown;
use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BreakdownCloseTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected Machine $machine;

    protected Breakdown $openBreakdown;

    protected function setUp(): void
    {
        parent::setUp();

        $location = Location::query()->create([
            'location_code' => 'LOC-01',
            'location_name' => 'Line 1',
            'is_active' => true,
        ]);

        $this->machine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'MCH-001',
            'machine_name' => 'Mesin CNC Milling',
            'qr_token' => 'qr-mch-001',
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

        $this->openBreakdown = Breakdown::query()->create([
            'breakdown_code' => 'BRK-20260522-0001',
            'machine_id' => $this->machine->id,
            'machine_name_snapshot' => $this->machine->machine_name,
            'location_name_snapshot' => $location->location_name,
            'part_name_snapshot' => 'Gearbox',
            'problem' => 'Bunyi kasar',
            'status' => 'open',
            'breakdown_at' => now()->subHours(2),
            'created_by' => $this->operator->id,
            'created_by_name_snapshot' => $this->operator->name,
        ]);
    }

    public function test_admin_and_operator_can_open_close_breakdown_form(): void
    {
        $this->actingAs($this->admin)
            ->get("/breakdown/{$this->openBreakdown->id}/close")
            ->assertOk()
            ->assertSee('Close Breakdown');

        $this->actingAs($this->operator)
            ->get("/breakdown/{$this->openBreakdown->id}/close")
            ->assertOk()
            ->assertSee('Akar Masalah');
    }

    public function test_guest_cannot_open_close_breakdown_form(): void
    {
        $guestSession = GuestSession::query()->create([
            'guest_name' => 'Guest Prime',
            'session_id' => 'guest-session',
            'login_at' => now(),
        ]);

        $this->withSession([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ])->get("/breakdown/{$this->openBreakdown->id}/close")
            ->assertRedirect('/403');
    }

    public function test_close_breakdown_requires_all_fields(): void
    {
        $this->actingAs($this->operator)
            ->from("/breakdown/{$this->openBreakdown->id}/close")
            ->patch("/breakdown/{$this->openBreakdown->id}/close", [
                'root_cause' => '',
                'action_taken' => '',
                'countermeasure' => '',
                'closed_at' => '',
            ])
            ->assertRedirect("/breakdown/{$this->openBreakdown->id}/close")
            ->assertSessionHasErrors(['root_cause', 'action_taken', 'countermeasure', 'closed_at']);
    }

    public function test_close_breakdown_rejects_closed_at_before_breakdown_at(): void
    {
        $invalidClosedAt = $this->openBreakdown->breakdown_at->copy()->subMinutes(10)->format('Y-m-d H:i:s');

        $this->actingAs($this->admin)
            ->from("/breakdown/{$this->openBreakdown->id}/close")
            ->patch("/breakdown/{$this->openBreakdown->id}/close", [
                'root_cause' => 'Kerusakan belt',
                'action_taken' => 'Ganti belt',
                'countermeasure' => 'Periksa ketegangan belt harian',
                'closed_at' => $invalidClosedAt,
            ])
            ->assertRedirect("/breakdown/{$this->openBreakdown->id}/close")
            ->assertSessionHasErrors(['closed_at']);
    }

    public function test_admin_can_close_breakdown_and_persist_history_notification_and_downtime(): void
    {
        $closedAt = $this->openBreakdown->breakdown_at->copy()->addMinutes(90);

        $this->actingAs($this->admin)
            ->patch("/breakdown/{$this->openBreakdown->id}/close", [
                'root_cause' => 'Bearing aus',
                'action_taken' => 'Ganti bearing spindle',
                'countermeasure' => 'Tambah checklist inspeksi vibrasi',
                'closed_at' => $closedAt->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect("/machines/{$this->machine->machine_code}");

        $this->assertDatabaseHas('breakdowns', [
            'id' => $this->openBreakdown->id,
            'status' => 'closed',
            'root_cause' => 'Bearing aus',
            'action_taken' => 'Ganti bearing spindle',
            'countermeasure' => 'Tambah checklist inspeksi vibrasi',
            'closed_by' => $this->admin->id,
            'closed_by_name_snapshot' => $this->admin->name,
            'downtime_minutes' => 90,
        ]);

        $this->assertDatabaseHas('breakdown_history', [
            'breakdown_id' => $this->openBreakdown->id,
            'field_name' => 'Status Breakdown',
            'old_value' => 'open',
            'new_value' => 'closed',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notification_type' => 'breakdown_closed',
            'related_table' => 'breakdowns',
            'related_id' => $this->openBreakdown->id,
            'target_role' => 'admin',
        ]);

        $this->assertDatabaseHas('user_activity_logs', [
            'module_name' => 'breakdown_close',
            'action' => 'close_breakdown',
            'table_name' => 'breakdowns',
            'record_id' => $this->openBreakdown->id,
        ]);
    }

    public function test_closed_breakdown_cannot_be_submitted_again(): void
    {
        $this->openBreakdown->forceFill([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => $this->admin->id,
            'closed_by_name_snapshot' => $this->admin->name,
            'downtime_minutes' => 30,
        ])->save();

        $this->actingAs($this->operator)
            ->patch("/breakdown/{$this->openBreakdown->id}/close", [
                'root_cause' => 'x',
                'action_taken' => 'x',
                'countermeasure' => 'x',
                'closed_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect("/machines/{$this->machine->machine_code}");
    }

    public function test_closed_breakdown_is_not_shown_in_open_breakdown_cards(): void
    {
        $this->openBreakdown->forceFill([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => $this->admin->id,
            'closed_by_name_snapshot' => $this->admin->name,
            'downtime_minutes' => 45,
        ])->save();

        $this->actingAs($this->operator)
            ->get("/machines/{$this->machine->machine_code}")
            ->assertOk()
            ->assertDontSee($this->openBreakdown->breakdown_code)
            ->assertSee('Tidak ada breakdown OPEN');
    }
}

