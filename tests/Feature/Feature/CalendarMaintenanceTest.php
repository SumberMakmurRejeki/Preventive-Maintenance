<?php

namespace Tests\Feature\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CalendarMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected Machine $machineA;

    protected Machine $machineB;

    protected PmScheduleDate $pmScheduled;

    protected PmScheduleDate $pmWaitingReview;

    protected PmScheduleDate $pmApproved;

    protected Breakdown $breakdownOpen;

    protected Breakdown $breakdownClosed;

    protected function setUp(): void
    {
        parent::setUp();

        $locationA = Location::query()->create([
            'location_code' => 'LOC-A',
            'location_name' => 'Line Produksi A',
            'is_active' => true,
        ]);

        $locationB = Location::query()->create([
            'location_code' => 'LOC-B',
            'location_name' => 'Line Produksi B',
            'is_active' => true,
        ]);

        $this->machineA = Machine::query()->create([
            'location_id' => $locationA->id,
            'machine_code' => 'MCH-01',
            'machine_name' => 'Packaging Machine V2',
            'qr_token' => 'qr-mch-01',
            'is_active' => true,
        ]);

        $this->machineB = Machine::query()->create([
            'location_id' => $locationB->id,
            'machine_code' => 'MCH-02',
            'machine_name' => 'CNC Milling Alpha',
            'qr_token' => 'qr-mch-02',
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
            'machine_id' => $this->machineA->id,
            'assigned_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        $schedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $checksheetMachine->id,
            'frequency_type' => 'daily',
            'start_date' => now()->subMonth()->toDateString(),
            'generate_until' => now()->addMonth()->toDateString(),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->pmScheduled = PmScheduleDate::query()->create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $this->machineA->id,
            'scheduled_date' => '2026-05-10',
            'status' => 'scheduled',
        ]);

        $this->pmWaitingReview = PmScheduleDate::query()->create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $this->machineA->id,
            'scheduled_date' => '2026-05-12',
            'status' => 'waiting_review',
        ]);

        $this->pmApproved = PmScheduleDate::query()->create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $this->machineB->id,
            'scheduled_date' => '2026-05-15',
            'status' => 'approved',
        ]);

        PmExecution::query()->create([
            'pm_schedule_date_id' => $this->pmWaitingReview->id,
            'machine_id' => $this->machineA->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'waiting_review',
            'submitted_at' => now()->subDay(),
        ]);

        PmExecution::query()->create([
            'pm_schedule_date_id' => $this->pmApproved->id,
            'machine_id' => $this->machineB->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'approved',
            'submitted_at' => now()->subHours(12),
            'approved_at' => now()->subHours(6),
            'approved_by' => $this->admin->id,
            'approved_by_name_snapshot' => $this->admin->name,
        ]);

        $this->breakdownOpen = Breakdown::query()->create([
            'breakdown_code' => 'BD-OPEN-001',
            'machine_id' => $this->machineA->id,
            'machine_name_snapshot' => $this->machineA->machine_name,
            'location_name_snapshot' => $locationA->location_name,
            'problem' => 'Motor overheat',
            'status' => 'open',
            'breakdown_at' => '2026-05-18 08:00:00',
            'created_by' => $this->operator->id,
            'created_by_name_snapshot' => $this->operator->name,
        ]);

        $this->breakdownClosed = Breakdown::query()->create([
            'breakdown_code' => 'BD-CLOSE-001',
            'machine_id' => $this->machineB->id,
            'machine_name_snapshot' => $this->machineB->machine_name,
            'location_name_snapshot' => $locationB->location_name,
            'problem' => 'Hydraulic leak',
            'status' => 'closed',
            'breakdown_at' => '2026-05-20 09:00:00',
            'closed_at' => '2026-05-20 12:00:00',
            'created_by' => $this->operator->id,
            'created_by_name_snapshot' => $this->operator->name,
            'closed_by' => $this->admin->id,
            'closed_by_name_snapshot' => $this->admin->name,
            'downtime_minutes' => 180,
        ]);
    }

    public function test_admin_can_open_calendar_and_receive_clickable_events(): void
    {
        $this->actingAs($this->admin)
            ->get('/calendar')
            ->assertOk()
            ->assertSee('Kalender Maintenance');

        $response = $this->actingAs($this->admin)
            ->getJson('/calendar/events?start=2026-05-01&end=2026-05-31');

        $response->assertOk();
        $events = $response->json();

        $this->assertNotEmpty($events);
        $this->assertTrue(collect($events)->every(fn (array $event): bool => isset($event['url']) && $event['url'] !== null));
    }

    public function test_operator_can_open_calendar_but_events_are_not_clickable(): void
    {
        $this->actingAs($this->operator)
            ->get('/calendar')
            ->assertOk()
            ->assertSee('Kalender Maintenance');

        $response = $this->actingAs($this->operator)
            ->getJson('/calendar/events?start=2026-05-01&end=2026-05-31');

        $response->assertOk();
        $events = $response->json();

        $this->assertNotEmpty($events);
        $this->assertTrue(collect($events)->every(fn (array $event): bool => !isset($event['url']) || $event['url'] === null));
    }

    public function test_guest_cannot_access_calendar_and_events(): void
    {
        $guestSession = GuestSession::query()->create([
            'guest_name' => 'Guest PRIME',
            'session_id' => 'guest-session',
            'login_at' => now(),
        ]);

        $this->withSession([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ])->get('/calendar')->assertRedirect('/403');

        $this->withSession([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ])->get('/calendar/events?start=2026-05-01&end=2026-05-31')->assertRedirect('/403');
    }

    public function test_pm_events_include_expected_title_status_and_redirect_rule(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/calendar/events?event_type=pm&start=2026-05-01&end=2026-05-31');

        $response->assertOk();

        $events = collect($response->json());

        $scheduledEvent = $events->firstWhere('id', 'pm-' . $this->pmScheduled->id);
        $waitingReviewEvent = $events->firstWhere('id', 'pm-' . $this->pmWaitingReview->id);

        $this->assertNotNull($scheduledEvent);
        $this->assertSame('PM - Packaging Machine V2', $scheduledEvent['title']);
        $this->assertSame('scheduled', $scheduledEvent['extendedProps']['status']);
        $this->assertStringContainsString('/machines/MCH-01', $scheduledEvent['url']);

        $this->assertNotNull($waitingReviewEvent);
        $this->assertSame('waiting_review', $waitingReviewEvent['extendedProps']['status']);
        $this->assertStringContainsString('/pm/review/', $waitingReviewEvent['url']);
    }

    public function test_breakdown_events_include_expected_title_and_closed_end_date(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/calendar/events?event_type=breakdown&start=2026-05-01&end=2026-05-31');

        $response->assertOk();

        $events = collect($response->json());
        $closedEvent = $events->firstWhere('id', 'bd-' . $this->breakdownClosed->id);

        $this->assertNotNull($closedEvent);
        $this->assertSame('Breakdown - CNC Milling Alpha', $closedEvent['title']);
        $this->assertSame('closed', $closedEvent['extendedProps']['status']);
        $this->assertSame($this->breakdownClosed->closed_at?->toIso8601String(), $closedEvent['end']);
    }

    public function test_calendar_filters_by_type_location_machine_status_and_range(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/calendar/events?event_type=breakdown&status=open&start=2026-05-01&end=2026-05-31')
            ->assertOk()
            ->assertJsonCount(1);

        $locationFiltered = $this->actingAs($this->admin)
            ->getJson('/calendar/events?event_type=pm&location_id=' . $this->machineB->location_id . '&start=2026-05-01&end=2026-05-31')
            ->assertOk()
            ->json();

        $this->assertCount(1, $locationFiltered);
        $this->assertSame('PM - CNC Milling Alpha', $locationFiltered[0]['title']);

        $machineFiltered = $this->actingAs($this->admin)
            ->getJson('/calendar/events?machine_id=' . $this->machineA->id . '&start=2026-05-01&end=2026-05-31')
            ->assertOk()
            ->json();

        $this->assertTrue(collect($machineFiltered)->every(
            fn (array $event): bool => str_contains((string) $event['title'], 'Packaging Machine V2')
        ));

        $rangeFiltered = $this->actingAs($this->admin)
            ->getJson('/calendar/events?start=2026-05-20&end=2026-05-21')
            ->assertOk()
            ->json();

        $this->assertCount(2, $rangeFiltered);
        $this->assertTrue(collect($rangeFiltered)->contains(
            fn (array $event): bool => $event['id'] === 'bd-' . $this->breakdownClosed->id
        ));
    }

    public function test_calendar_returns_validation_error_for_invalid_date_range(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/calendar/events?start=2026-05-31&end=2026-05-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end']);
    }

    public function test_calendar_page_contains_loading_empty_error_and_responsive_markers(): void
    {
        $this->actingAs($this->admin)
            ->get('/calendar')
            ->assertOk()
            ->assertSee('data-calendar-loading', false)
            ->assertSee('data-calendar-empty', false)
            ->assertSee('data-calendar-error', false)
            ->assertSee('sm:p-4');
    }
}
