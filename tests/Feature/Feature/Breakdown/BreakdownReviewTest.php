<?php

namespace Tests\Feature\Feature\Breakdown;

use App\Models\Breakdown;
use App\Models\BreakdownHistory;
use App\Models\BreakdownMedia;
use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BreakdownReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected Machine $machineA;

    protected Machine $machineB;

    protected Breakdown $openBreakdown;

    protected Breakdown $closedBreakdown;

    protected function setUp(): void
    {
        parent::setUp();

        $locationA = Location::query()->create([
            'location_code' => 'LOC-01',
            'location_name' => 'Line 1',
            'is_active' => true,
        ]);

        $locationB = Location::query()->create([
            'location_code' => 'LOC-02',
            'location_name' => 'Line 2',
            'is_active' => true,
        ]);

        $this->machineA = Machine::query()->create([
            'location_id' => $locationA->id,
            'machine_code' => 'MCH-001',
            'machine_name' => 'CNC Milling',
            'qr_token' => 'qr-001',
            'is_active' => true,
        ]);

        $this->machineB = Machine::query()->create([
            'location_id' => $locationB->id,
            'machine_code' => 'MCH-002',
            'machine_name' => 'Sealing Machine',
            'qr_token' => 'qr-002',
            'is_active' => true,
        ]);

        $this->admin = User::query()->create([
            'name' => 'Admin SPV',
            'username' => 'admin.prime',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->operator = User::query()->create([
            'name' => 'Budi Santoso',
            'username' => 'operator.prime',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'is_active' => true,
        ]);

        $this->openBreakdown = Breakdown::query()->create([
            'breakdown_code' => 'BRK-20260522-01',
            'machine_id' => $this->machineA->id,
            'machine_name_snapshot' => 'CNC Milling',
            'location_name_snapshot' => 'Line 1',
            'part_name_snapshot' => 'Spindle',
            'problem' => 'Vibration high and abnormal noise.',
            'open_note' => 'Operator stop aman.',
            'status' => 'open',
            'breakdown_at' => now()->subHours(3),
            'created_by' => $this->operator->id,
            'created_by_name_snapshot' => $this->operator->name,
        ]);

        $this->closedBreakdown = Breakdown::query()->create([
            'breakdown_code' => 'BRK-20260521-04',
            'machine_id' => $this->machineB->id,
            'machine_name_snapshot' => 'Sealing Machine',
            'location_name_snapshot' => 'Line 2',
            'part_name_snapshot' => 'Heater Block',
            'problem' => 'Temperature drops frequently.',
            'status' => 'closed',
            'breakdown_at' => now()->subDay(),
            'closed_at' => now()->subDay()->addHours(2)->addMinutes(30),
            'root_cause' => 'Heater element burnt out.',
            'action_taken' => 'Replace heater element',
            'countermeasure' => 'Install stabilizer',
            'created_by' => $this->operator->id,
            'created_by_name_snapshot' => 'Andi Pratama',
            'closed_by' => $this->admin->id,
            'closed_by_name_snapshot' => $this->admin->name,
            'downtime_minutes' => 150,
        ]);

        BreakdownMedia::query()->create([
            'breakdown_id' => $this->closedBreakdown->id,
            'file_type' => 'video',
            'file_path' => 'breakdown-media/heater_test.mp4',
            'original_file_path' => 'breakdown-media/heater_test.mp4',
            'file_name' => 'heater_test.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 1000,
            'original_file_size' => 1000,
            'compressed_file_size' => 1000,
            'note' => 'Video test kebocoran.',
            'uploaded_by' => $this->operator->id,
            'uploaded_by_name_snapshot' => $this->operator->name,
        ]);
    }

    public function test_only_admin_can_access_breakdown_review_routes(): void
    {
        $this->actingAs($this->admin)->get('/breakdown/review')->assertOk();

        $this->actingAs($this->operator)->get('/breakdown/review')->assertRedirect('/403');

        $guestSession = GuestSession::query()->create([
            'guest_name' => 'Guest Prime',
            'session_id' => 'guest-session',
            'login_at' => now(),
        ]);

        $this->withSession([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ])->get('/breakdown/review')->assertRedirect('/403');
    }

    public function test_list_displays_open_and_closed_breakdown(): void
    {
        $this->actingAs($this->admin)
            ->get('/breakdown/review')
            ->assertOk()
            ->assertSee('BRK-20260522-01')
            ->assertSee('BRK-20260521-04');
    }

    public function test_search_and_filter_status_work(): void
    {
        $this->actingAs($this->admin)
            ->get('/breakdown/review?search=Sealing')
            ->assertOk()
            ->assertSee('BRK-20260521-04')
            ->assertDontSee('BRK-20260522-01');

        $this->actingAs($this->admin)
            ->get('/breakdown/review?status=open')
            ->assertOk()
            ->assertSee('BRK-20260522-01')
            ->assertDontSee('BRK-20260521-04');
    }

    public function test_detail_open_and_closed_views_show_expected_data(): void
    {
        $this->actingAs($this->admin)
            ->get("/breakdown/review/{$this->openBreakdown->id}")
            ->assertOk()
            ->assertSee('Vibration high and abnormal noise.')
            ->assertSee('Breakdown belum ditutup');

        $this->actingAs($this->admin)
            ->get("/breakdown/review/{$this->closedBreakdown->id}")
            ->assertOk()
            ->assertSee('Heater element burnt out.')
            ->assertSee('heater_test.mp4');
    }

    public function test_admin_can_edit_breakdown_and_history_is_recorded(): void
    {
        $this->actingAs($this->admin)
            ->put("/breakdown/review/{$this->openBreakdown->id}", [
                'status' => 'closed',
                'problem' => 'Vibration critical at high RPM.',
                'open_note' => 'Updated note',
                'breakdown_at' => now()->subHours(4)->format('Y-m-d H:i:s'),
                'root_cause' => 'Bearing wear out',
                'action_taken' => 'Replace bearing',
                'countermeasure' => 'Add lubrication schedule',
                'closed_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
                'change_note' => 'Koreksi data sesuai investigasi.',
            ])
            ->assertRedirect("/breakdown/review/{$this->openBreakdown->id}");

        $this->assertDatabaseHas('breakdowns', [
            'id' => $this->openBreakdown->id,
            'status' => 'closed',
            'problem' => 'Vibration critical at high RPM.',
        ]);

        $this->assertDatabaseHas('breakdown_history', [
            'breakdown_id' => $this->openBreakdown->id,
            'field_name' => 'problem',
            'new_value' => 'Vibration critical at high RPM.',
            'change_note' => 'Koreksi data sesuai investigasi.',
        ]);
    }

    public function test_change_note_is_required_for_edit(): void
    {
        $this->actingAs($this->admin)
            ->from("/breakdown/review/{$this->openBreakdown->id}/edit")
            ->put("/breakdown/review/{$this->openBreakdown->id}", [
                'status' => 'open',
                'problem' => 'abc',
                'breakdown_at' => now()->subHours(4)->format('Y-m-d H:i:s'),
                'change_note' => '',
            ])
            ->assertRedirect("/breakdown/review/{$this->openBreakdown->id}/edit")
            ->assertSessionHasErrors(['change_note']);
    }

    public function test_admin_can_delete_open_and_closed_with_cascade(): void
    {
        BreakdownHistory::query()->create([
            'breakdown_id' => $this->closedBreakdown->id,
            'changed_by' => $this->admin->id,
            'changed_at' => now(),
            'field_name' => 'problem',
            'old_value' => 'old',
            'new_value' => 'new',
            'change_note' => 'test',
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->delete("/breakdown/review/{$this->closedBreakdown->id}")
            ->assertRedirect('/breakdown/review');

        $this->assertDatabaseMissing('breakdowns', ['id' => $this->closedBreakdown->id]);
        $this->assertDatabaseMissing('breakdown_media', ['breakdown_id' => $this->closedBreakdown->id]);
        $this->assertDatabaseMissing('breakdown_history', ['breakdown_id' => $this->closedBreakdown->id]);

        $this->assertDatabaseHas('user_activity_logs', [
            'module_name' => 'breakdown_review',
            'action' => 'delete',
            'table_name' => 'breakdowns',
        ]);
    }
}

