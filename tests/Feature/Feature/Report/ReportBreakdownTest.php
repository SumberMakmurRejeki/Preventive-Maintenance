<?php

namespace Tests\Feature\Feature\Report;

use App\Models\Breakdown;
use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmChecksheetPart;
use App\Models\ReportExport;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportBreakdownTest extends TestCase
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
            'machine_code' => 'M-A-01',
            'machine_name' => 'Mesin A',
            'qr_token' => 'qr-a-01',
            'is_active' => true,
        ]);
        $this->machineB = Machine::query()->create([
            'location_id' => $locationB->id,
            'machine_code' => 'M-B-01',
            'machine_name' => 'Mesin B',
            'qr_token' => 'qr-b-01',
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

        $this->openBreakdown = $this->createBreakdown(
            machine: $this->machineA,
            code: 'BRK-OPEN-001',
            breakdownAt: now()->subHours(3),
            status: 'open',
            partName: 'Part Gearbox',
            createdBy: $this->operator,
        );
        $this->closedBreakdown = $this->createBreakdown(
            machine: $this->machineB,
            code: 'BRK-CLOSED-001',
            breakdownAt: now()->subHours(6),
            status: 'closed',
            partName: 'Part Motor',
            createdBy: $this->operator,
            closedBy: $this->admin,
            closedAt: now()->subHours(4),
            downtimeMinutes: 120,
        );
    }

    public function test_admin_can_access_report_breakdown_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/report/breakdown');

        $response->assertOk();
        $response->assertSee('Report Breakdown');
        $response->assertSee('BRK-OPEN-001');
        $response->assertSee('BRK-CLOSED-001');
    }

    public function test_operator_and_guest_cannot_access_report_breakdown_page(): void
    {
        $this->actingAs($this->operator)
            ->get('/report/breakdown')
            ->assertRedirect('/403');

        $guestSession = GuestSession::query()->create([
            'guest_name' => 'Guest PRIME',
            'session_id' => 'guest-session-breakdown',
            'login_at' => now(),
        ]);

        $this->withSession([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ])->get('/report/breakdown')
            ->assertRedirect('/403');
    }

    public function test_report_breakdown_filter_by_location_status_and_search(): void
    {
        $response = $this->actingAs($this->admin)->get('/report/breakdown?'.http_build_query([
            'location_id' => $this->machineB->location_id,
            'status' => 'CLOSED',
            'search' => 'motor',
        ]));

        $response->assertOk();
        $response->assertSee('BRK-CLOSED-001');
        $response->assertSee('Menampilkan 1-1 dari total 1 data');
    }

    public function test_report_breakdown_filter_by_breakdown_date_only_returns_matching_items(): void
    {
        $response = $this->actingAs($this->admin)->get('/report/breakdown?'.http_build_query([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->toDateString(),
            'status' => 'CLOSED',
        ]));

        $response->assertOk();
        $response->assertSee('BRK-CLOSED-001');
        $response->assertDontSee('BRK-OPEN-001');
    }

    public function test_report_breakdown_pagination_is_10_rows_max(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->createBreakdown(
                machine: $this->machineA,
                code: 'BRK-PAG-'.$i,
                breakdownAt: now()->subDays($i + 1),
                status: 'closed',
                partName: 'Part '.$i,
                createdBy: $this->operator,
                closedBy: $this->admin,
                closedAt: now()->subDays($i + 1)->addHours(2),
                downtimeMinutes: 120,
            );
        }

        $response = $this->actingAs($this->admin)->get('/report/breakdown');
        $response->assertOk();
        $response->assertSee('Menampilkan 1-10 dari total 13 data');
    }

    public function test_report_breakdown_invalid_date_range_shows_error_message(): void
    {
        $response = $this->actingAs($this->admin)->get('/report/breakdown?start_date=2026-05-10&end_date=2026-05-01');

        $response->assertSessionHasErrors('end_date');
    }

    public function test_report_breakdown_search_matches_part_and_problem_text(): void
    {
        $response = $this->actingAs($this->admin)->get('/report/breakdown?search=gearbox');

        $response->assertOk();
        $response->assertSee('BRK-OPEN-001');
        $response->assertDontSee('BRK-CLOSED-001');
    }

    public function test_report_breakdown_export_pdf_and_excel_create_report_export_rows(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/report/breakdown/export/pdf', [
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString(),
                'status' => 'CLOSED',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('report_exports', [
            'report_type' => 'breakdown',
            'file_type' => 'pdf',
            'status' => 'completed',
            'requested_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson('/report/breakdown/export/excel', [
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString(),
                'status' => 'CLOSED',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('report_exports', [
            'report_type' => 'breakdown',
            'file_type' => 'excel',
            'status' => 'completed',
            'requested_by' => $this->admin->id,
        ]);
    }

    public function test_report_breakdown_export_empty_returns_warning_response(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/report/breakdown/export/pdf', [
                'start_date' => now()->subYears(4)->toDateString(),
                'end_date' => now()->subYears(4)->addDay()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tidak ada data breakdown untuk diexport.');
    }

    public function test_report_breakdown_export_respects_active_filters(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/report/breakdown/export/excel', [
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString(),
                'location_id' => $this->machineB->location_id,
                'status' => 'CLOSED',
            ])
            ->assertOk();

        $export = ReportExport::query()
            ->where('report_type', 'breakdown')
            ->where('file_type', 'excel')
            ->latest('id')
            ->first();

        $this->assertNotNull($export);
        $this->assertSame($this->machineB->location_id, $export->filter_data['location_id']);
        $this->assertSame('CLOSED', $export->filter_data['status']);
    }

    public function test_report_breakdown_export_failed_marks_status_failed(): void
    {
        Pdf::shouldReceive('loadView')
            ->once()
            ->andThrow(new \RuntimeException('PDF gagal'));

        $this->actingAs($this->admin)
            ->postJson('/report/breakdown/export/pdf', [
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString(),
            ])
            ->assertStatus(500)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('report_exports', [
            'report_type' => 'breakdown',
            'file_type' => 'pdf',
            'status' => 'failed',
            'requested_by' => $this->admin->id,
        ]);
    }

    private function createBreakdown(
        Machine $machine,
        string $code,
        Carbon $breakdownAt,
        string $status,
        string $partName,
        User $createdBy,
        ?User $closedBy = null,
        ?Carbon $closedAt = null,
        ?int $downtimeMinutes = null,
    ): Breakdown {
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'CHK-'.$machine->machine_code.'-'.substr((string) str()->uuid(), 0, 6),
            'checksheet_name' => 'Checksheet '.$machine->machine_name,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $checksheetMachine = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $machine->id,
            'created_by' => $this->admin->id,
        ]);

        $part = PmChecksheetPart::query()->create([
            'pm_checksheet_machine_id' => $checksheetMachine->id,
            'part_name' => $partName,
            'part_sequence' => 1,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        return Breakdown::query()->create([
            'breakdown_code' => $code,
            'machine_id' => $machine->id,
            'pm_checksheet_part_id' => $part->id,
            'machine_name_snapshot' => $machine->machine_name,
            'location_name_snapshot' => $machine->location?->location_name,
            'part_name_snapshot' => $partName,
            'problem' => 'Problem '.$code,
            'status' => $status,
            'breakdown_at' => $breakdownAt,
            'created_by' => $createdBy->id,
            'created_by_name_snapshot' => $createdBy->name,
            'root_cause' => $status === 'closed' ? 'Root cause '.$code : null,
            'action_taken' => $status === 'closed' ? 'Action '.$code : null,
            'countermeasure' => $status === 'closed' ? 'Counter '.$code : null,
            'closed_at' => $closedAt,
            'closed_by' => $closedBy?->id,
            'closed_by_name_snapshot' => $closedBy?->name,
            'downtime_minutes' => $downtimeMinutes,
        ]);
    }
}
