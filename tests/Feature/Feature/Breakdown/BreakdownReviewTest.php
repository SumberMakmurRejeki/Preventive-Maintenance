<?php

namespace Tests\Feature\Feature\Breakdown;

use App\Models\Breakdown;
use App\Models\BreakdownHistory;
use App\Models\BreakdownMedia;
use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\PrimeNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Scenario A: Persisted OPEN breakdown delete must be rejected.
     * OPEN breakdown is active transaction evidence and cannot be hard-deleted.
     */
    public function test_admin_cannot_delete_persisted_open_breakdown(): void
    {
        // Fake public disk untuk physical file preservation assertion
        Storage::fake('public');

        // Add media to OPEN breakdown untuk preservation assertion
        $openMediaPath = 'breakdown-media/open_test.jpg';
        Storage::disk('public')->put($openMediaPath, 'fake-image-content');

        BreakdownMedia::query()->create([
            'breakdown_id' => $this->openBreakdown->id,
            'file_type' => 'photo',
            'file_path' => $openMediaPath,
            'original_file_path' => $openMediaPath,
            'file_name' => 'open_test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 500,
            'original_file_size' => 500,
            'compressed_file_size' => 500,
            'note' => 'Test media for OPEN breakdown',
            'uploaded_by' => $this->operator->id,
            'uploaded_by_name_snapshot' => $this->operator->name,
        ]);

        // Add history to OPEN breakdown
        BreakdownHistory::query()->create([
            'breakdown_id' => $this->openBreakdown->id,
            'changed_by' => $this->admin->id,
            'changed_at' => now(),
            'field_name' => 'problem',
            'old_value' => 'old problem',
            'new_value' => 'new problem',
            'change_note' => 'test edit',
            'created_at' => now(),
        ]);

        // Add notification for OPEN breakdown
        PrimeNotification::query()->create([
            'title' => 'Breakdown Open',
            'message' => 'Test notification',
            'related_table' => 'breakdowns',
            'related_id' => $this->openBreakdown->id,
            'notification_type' => 'breakdown_open',
        ]);

        // Capture pre-delete state
        $preDeleteBreakdown = $this->openBreakdown->fresh();
        $preDeleteMediaCount = BreakdownMedia::query()->where('breakdown_id', $this->openBreakdown->id)->count();
        $preDeleteHistoryCount = BreakdownHistory::query()->where('breakdown_id', $this->openBreakdown->id)->count();
        $preDeleteNotificationCount = PrimeNotification::query()
            ->where('related_table', 'breakdowns')
            ->where('related_id', $this->openBreakdown->id)
            ->count();

        // Assert physical file exists before delete attempt
        $this->assertTrue(Storage::disk('public')->exists($openMediaPath));

        // Attempt DELETE
        $response = $this->actingAs($this->admin)
            ->delete("/breakdown/review/{$this->openBreakdown->id}");

        // Assert rejection with redirect
        $response->assertRedirect('/breakdown/review');
        $response->assertSessionHas('flash_error');

        // Assert business message
        $this->assertStringContainsString(
            'Data breakdown yang sudah tercatat tidak dapat dihapus karena merupakan riwayat kejadian mesin.',
            session('flash_error')
        );

        // Assert breakdown row preserved
        $this->assertDatabaseHas('breakdowns', [
            'id' => $this->openBreakdown->id,
            'status' => 'open',
        ]);

        // Assert status unchanged
        $postDeleteBreakdown = $this->openBreakdown->fresh();
        $this->assertEquals('open', $postDeleteBreakdown->status);
        $this->assertEquals($preDeleteBreakdown->problem, $postDeleteBreakdown->problem);
        $this->assertEquals($preDeleteBreakdown->machine_id, $postDeleteBreakdown->machine_id);
        $this->assertEquals($preDeleteBreakdown->updated_at->toDateTimeString(), $postDeleteBreakdown->updated_at->toDateTimeString());

        // Assert media DB row preserved
        $this->assertEquals($preDeleteMediaCount, BreakdownMedia::query()->where('breakdown_id', $this->openBreakdown->id)->count());

        // Assert physical file preserved
        $this->assertTrue(Storage::disk('public')->exists($openMediaPath));

        // Assert history preserved
        $this->assertEquals($preDeleteHistoryCount, BreakdownHistory::query()->where('breakdown_id', $this->openBreakdown->id)->count());

        // Assert notifications preserved
        $this->assertEquals($preDeleteNotificationCount, PrimeNotification::query()
            ->where('related_table', 'breakdowns')
            ->where('related_id', $this->openBreakdown->id)
            ->count());

        // Assert NO activity log for rejected delete
        $this->assertDatabaseMissing('user_activity_logs', [
            'module_name' => 'breakdown_review',
            'action' => 'delete',
            'table_name' => 'breakdowns',
            'record_id' => $this->openBreakdown->id,
        ]);
    }

    /**
     * Scenario B: Persisted CLOSED breakdown delete must be rejected.
     * CLOSED breakdown is finalized historical evidence and cannot be hard-deleted.
     */
    public function test_admin_cannot_delete_persisted_closed_breakdown(): void
    {
        // Fake public disk untuk physical file preservation assertion
        Storage::fake('public');

        // Create physical file untuk CLOSED breakdown media (heater_test.mp4 dari setUp)
        $closedMediaPath = 'breakdown-media/heater_test.mp4';
        Storage::disk('public')->put($closedMediaPath, 'fake-video-content');

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

        // Add notification for CLOSED breakdown
        PrimeNotification::query()->create([
            'title' => 'Breakdown Closed',
            'message' => 'Test notification',
            'related_table' => 'breakdowns',
            'related_id' => $this->closedBreakdown->id,
            'notification_type' => 'breakdown_closed',
        ]);

        // Capture pre-delete state
        $preDeleteBreakdown = $this->closedBreakdown->fresh();
        $preDeleteMediaCount = BreakdownMedia::query()->where('breakdown_id', $this->closedBreakdown->id)->count();
        $preDeleteHistoryCount = BreakdownHistory::query()->where('breakdown_id', $this->closedBreakdown->id)->count();
        $preDeleteNotificationCount = PrimeNotification::query()
            ->where('related_table', 'breakdowns')
            ->where('related_id', $this->closedBreakdown->id)
            ->count();

        // Assert physical file exists before delete attempt
        $this->assertTrue(Storage::disk('public')->exists($closedMediaPath));

        // Attempt DELETE
        $response = $this->actingAs($this->admin)
            ->delete("/breakdown/review/{$this->closedBreakdown->id}");

        // Assert rejection with redirect
        $response->assertRedirect('/breakdown/review');
        $response->assertSessionHas('flash_error');

        // Assert business message
        $this->assertStringContainsString(
            'Data breakdown yang sudah tercatat tidak dapat dihapus karena merupakan riwayat kejadian mesin.',
            session('flash_error')
        );

        // Assert breakdown row preserved
        $this->assertDatabaseHas('breakdowns', [
            'id' => $this->closedBreakdown->id,
            'status' => 'closed',
        ]);

        // Assert status and final data unchanged
        $postDeleteBreakdown = $this->closedBreakdown->fresh();
        $this->assertEquals('closed', $postDeleteBreakdown->status);
        $this->assertEquals($preDeleteBreakdown->root_cause, $postDeleteBreakdown->root_cause);
        $this->assertEquals($preDeleteBreakdown->action_taken, $postDeleteBreakdown->action_taken);
        $this->assertEquals($preDeleteBreakdown->countermeasure, $postDeleteBreakdown->countermeasure);
        $this->assertEquals($preDeleteBreakdown->downtime_minutes, $postDeleteBreakdown->downtime_minutes);
        $this->assertEquals($preDeleteBreakdown->closed_at->toDateTimeString(), $postDeleteBreakdown->closed_at->toDateTimeString());

        // Assert media DB row preserved
        $this->assertEquals($preDeleteMediaCount, BreakdownMedia::query()->where('breakdown_id', $this->closedBreakdown->id)->count());

        // Assert physical file preserved
        $this->assertTrue(Storage::disk('public')->exists($closedMediaPath));

        // Assert history preserved
        $this->assertEquals($preDeleteHistoryCount, BreakdownHistory::query()->where('breakdown_id', $this->closedBreakdown->id)->count());

        // Assert notifications preserved
        $this->assertEquals($preDeleteNotificationCount, PrimeNotification::query()
            ->where('related_table', 'breakdowns')
            ->where('related_id', $this->closedBreakdown->id)
            ->count());

        // Assert NO activity log for rejected delete
        $this->assertDatabaseMissing('user_activity_logs', [
            'module_name' => 'breakdown_review',
            'action' => 'delete',
            'table_name' => 'breakdowns',
            'record_id' => $this->closedBreakdown->id,
        ]);
    }

    /**
     * Scenario C: Non-admin users cannot access delete route.
     * Authorization middleware must reject non-admin delete attempts.
     */
    public function test_operator_cannot_delete_breakdown(): void
    {
        $response = $this->actingAs($this->operator)
            ->delete("/breakdown/review/{$this->openBreakdown->id}");

        // Assert redirect (operator not authorized, middleware redirects)
        $response->assertRedirect();

        // Assert breakdown still exists
        $this->assertDatabaseHas('breakdowns', [
            'id' => $this->openBreakdown->id,
            'status' => 'open',
        ]);
    }

    /**
     * Scenario D: Valid OPEN workflow regression.
     * Delete protection must not break valid edit and close workflows.
     */
    public function test_admin_can_still_edit_open_breakdown(): void
    {
        $updatePayload = [
            'problem' => 'Updated problem description',
            'open_note' => 'Updated open note',
            'breakdown_at' => $this->openBreakdown->breakdown_at->format('Y-m-d H:i:s'),
            'status' => 'open',
            'change_note' => 'Test edit after protection',
        ];

        $response = $this->actingAs($this->admin)
            ->put("/breakdown/review/{$this->openBreakdown->id}", $updatePayload);

        $response->assertRedirect("/breakdown/review/{$this->openBreakdown->id}");

        // Assert update succeeded
        $this->assertDatabaseHas('breakdowns', [
            'id' => $this->openBreakdown->id,
            'problem' => 'Updated problem description',
            'open_note' => 'Updated open note',
            'status' => 'open',
        ]);

        // Assert history recorded
        $this->assertDatabaseHas('breakdown_history', [
            'breakdown_id' => $this->openBreakdown->id,
            'field_name' => 'problem',
            'new_value' => 'Updated problem description',
        ]);
    }

    /**
     * Scenario E: CLOSED breakdown can still be viewed.
     * Delete protection must not break read access to historical breakdowns.
     */
    public function test_admin_can_view_closed_breakdown(): void
    {
        $response = $this->actingAs($this->admin)
            ->get("/breakdown/review/{$this->closedBreakdown->id}");

        $response->assertOk();
        $response->assertViewIs('pages.breakdown.review.show');
        $response->assertViewHas('breakdown.id', $this->closedBreakdown->id);
    }
}

