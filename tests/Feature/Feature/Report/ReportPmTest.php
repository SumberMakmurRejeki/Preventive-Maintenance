<?php

namespace Tests\Feature\Feature\Report;

use App\Exports\PM\ReportPmExport;
use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmExecution;
use App\Models\PmExecutionItem;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Report\ReportPmService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportPmTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected Machine $machineA;

    protected Machine $machineB;

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

        $this->createExecutionWithItem(
            machine: $this->machineA,
            scheduledDate: now()->subDay()->toDateString(),
            submittedAt: now()->subDay()->setTime(10, 30, 0),
            status: 'approved',
            scheduleStatus: 'approved',
            isWarning: false,
            partName: 'Part A',
            note: 'Catatan operator A',
        );

        $this->createExecutionWithItem(
            machine: $this->machineB,
            scheduledDate: now()->toDateString(),
            submittedAt: now()->setTime(10, 30, 0),
            status: 'waiting_review',
            scheduleStatus: 'waiting_review',
            isWarning: true,
            partName: 'Part B',
            note: 'Perlu dicek ulang di shift malam',
        );

        $this->createScheduleOnly(
            machine: $this->machineB,
            scheduledDate: now()->addDay()->toDateString(),
            status: 'scheduled',
        );
    }

    /**
     * Slice B: Review dan Report harus memperlakukan bundle snapshot secara atomik.
     * Satu nilai yang hilang atau kosong tidak boleh menghasilkan identitas parsial.
     */
    public function test_review_and_report_require_four_usable_snapshot_values(): void
    {
        $service = app(ReportPmService::class);
        $executionId = (int) $service->exportRows([])->firstWhere('execution_id', '!=', null)->execution_id;
        $legacyLabel = 'Data historis tidak tersedia (legacy)';
        $completeSnapshot = [
            'machine_code_snapshot' => 'M-A-HIST',
            'machine_name_snapshot' => 'Mesin A Historis',
            'location_code_snapshot' => 'LOC-A-HIST',
            'location_name_snapshot' => 'Line Historis',
        ];
        $cases = [
            'missing_machine_code' => ['machine_code_snapshot' => null],
            'missing_machine_name' => ['machine_name_snapshot' => null],
            'missing_location_code' => ['location_code_snapshot' => null],
            'missing_location_name' => ['location_name_snapshot' => null],
            'two_of_four_usable' => ['machine_name_snapshot' => null, 'location_name_snapshot' => null],
            'one_of_four_usable' => ['machine_name_snapshot' => null, 'location_code_snapshot' => null, 'location_name_snapshot' => null],
            'zero_of_four_usable' => array_fill_keys(array_keys($completeSnapshot), null),
            'empty_string' => ['machine_code_snapshot' => ''],
            'whitespace_only' => ['machine_code_snapshot' => '   '],
        ];

        foreach ($cases as $case => $overrides) {
            PmExecution::query()->whereKey($executionId)->update(array_merge($completeSnapshot, $overrides));

            $this->actingAs($this->admin)
                ->get('/pm/review')
                ->assertOk()
                ->assertSee($legacyLabel);

            $row = $service->exportRows([])->firstWhere('execution_id', $executionId);
            $this->assertNotNull($row, $case);
            $this->assertSame($legacyLabel, $row->machine_code, $case);
            $this->assertSame($legacyLabel, $row->machine_name, $case);
            $this->assertSame($legacyLabel, $row->location_name, $case);

            $this->actingAs($this->admin)
                ->get('/report/pm')
                ->assertOk()
                ->assertSee($legacyLabel);
        }
    }

    /**
     * Slice B: identitas Report tetap memakai snapshot ketika Location master dihapus soft delete.
     */
    public function test_report_pm_snapshot_survives_current_location_soft_delete_for_all_output_surfaces(): void
    {
        $service = app(ReportPmService::class);
        $rowBefore = $service->exportRows([])->firstWhere('machine_code', 'M-A-01');
        $this->assertNotNull($rowBefore);

        $location = $this->machineA->location;
        $this->machineA->forceFill(['machine_code' => 'M-A-LIVE', 'machine_name' => 'Mesin A Live'])->save();
        $location->forceFill(['location_name' => 'Line Live'])->save();
        $location->delete();

        $row = $service->exportRows([])->firstWhere('execution_id', $rowBefore->execution_id);
        $this->assertNotNull($row);
        $this->assertSame('M-A-01', $row->machine_code);
        $this->assertSame('Mesin A', $row->machine_name);
        $this->assertSame('Line Produksi A', $row->location_name);

        $this->actingAs($this->admin)
            ->get('/report/pm')
            ->assertOk()
            ->assertSee('<td class="text-left font-semibold">M-A-01</td>', escape: false)
            ->assertSee('<td class="text-left">Line Produksi A</td>', escape: false)
            ->assertDontSee('M-A-LIVE')
            ->assertDontSee('Line Live');

        $mapped = (new ReportPmExport(collect([$row]), $service))->map($row);
        $this->assertContains('M-A-01', $mapped);
        $this->assertContains('Mesin A', $mapped);
        $this->assertContains('Line Produksi A', $mapped);
        $this->assertNotContains('M-A-LIVE', $mapped);
        $this->assertNotContains('Line Live', $mapped);

        $view = $this->view('pages.report.pm.pdf', [
            'rows' => collect([$row]),
            'reportPmService' => $service,
            'filters' => [],
            'summary' => ['total' => 1],
            'printedBy' => $this->admin->name,
        ]);
        $view->assertSee('M-A-01')
            ->assertSee('Mesin A')
            ->assertSee('Line Produksi A')
            ->assertDontSee('M-A-LIVE')
            ->assertDontSee('Line Live');
    }

    public function test_report_pm_export_rows_use_transaction_snapshots_after_master_changes(): void
    {
        $rowBefore = app(ReportPmService::class)->exportRows([])->firstWhere('machine_code', 'M-A-01');
        $this->assertNotNull($rowBefore);

        $location = $this->machineA->location;
        $this->machineA->forceFill(['machine_code' => 'M-A-LIVE', 'machine_name' => 'Mesin A Live'])->save();
        $location->forceFill(['location_name' => 'Line Live'])->save();

        $row = app(ReportPmService::class)->exportRows([])->firstWhere('execution_id', $rowBefore->execution_id);
        $this->assertNotNull($row);
        $this->assertSame('M-A-01', $row->machine_code);
        $this->assertSame('Mesin A', $row->machine_name);
        $this->assertSame('Line Produksi A', $row->location_name);
    }

    public function test_report_pm_incomplete_execution_snapshot_is_explicitly_legacy_unavailable(): void
    {
        $executionId = (int) app(ReportPmService::class)->exportRows([])->firstWhere('machine_code', 'M-A-01')->execution_id;
        PmExecution::query()->whereKey($executionId)->update([
            'machine_code_snapshot' => null,
            'machine_name_snapshot' => null,
            'location_code_snapshot' => null,
            'location_name_snapshot' => null,
        ]);

        $row = app(ReportPmService::class)->exportRows([])->firstWhere('execution_id', $executionId);
        $this->assertNotNull($row);
        $this->assertSame('Data historis tidak tersedia (legacy)', $row->machine_code);
        $this->assertSame('Data historis tidak tersedia (legacy)', $row->machine_name);
        $this->assertSame('Data historis tidak tersedia (legacy)', $row->location_name);
    }

    public function test_report_pm_html_uses_transaction_snapshots_after_master_changes(): void
    {
        $executionId = (int) app(ReportPmService::class)->exportRows([])->firstWhere('machine_code', 'M-A-01')->execution_id;

        $location = $this->machineA->location;
        $this->machineA->forceFill(['machine_code' => 'M-A-LIVE', 'machine_name' => 'Mesin A Live'])->save();
        $location->forceFill(['location_name' => 'Line Live'])->save();

        // Filter controls intentionally retain live master values for filtering.
        $this->actingAs($this->admin)
            ->get('/report/pm')
            ->assertOk()
            ->assertSee('M-A-01')
            ->assertSee('Mesin A')
            ->assertSee('Line Produksi A')
            ->assertDontSee('M-A-LIVE')
            ->assertDontSee('Mesin A Live');

        $this->assertNotNull($executionId);
    }

    public function test_report_pm_excel_map_uses_transaction_snapshots_after_master_changes(): void
    {
        $service = app(ReportPmService::class);
        $rowBefore = $service->exportRows([])->firstWhere('machine_code', 'M-A-01');

        $location = $this->machineA->location;
        $this->machineA->forceFill(['machine_code' => 'M-A-LIVE', 'machine_name' => 'Mesin A Live'])->save();
        $location->forceFill(['location_name' => 'Line Live'])->save();

        $row = $service->exportRows([])->firstWhere('execution_id', $rowBefore->execution_id);
        $this->assertNotNull($row);

        $mapped = (new ReportPmExport(collect([$row]), $service))->map($row);

        $this->assertContains('M-A-01', $mapped);
        $this->assertContains('Mesin A', $mapped);
        $this->assertContains('Line Produksi A', $mapped);
        $this->assertNotContains('M-A-LIVE', $mapped);
        $this->assertNotContains('Mesin A Live', $mapped);
        $this->assertNotContains('Line Live', $mapped);
    }

    public function test_report_pm_pdf_uses_transaction_snapshots_after_master_changes(): void
    {
        $service = app(ReportPmService::class);
        $rowBefore = $service->exportRows([])->firstWhere('machine_code', 'M-A-01');

        $location = $this->machineA->location;
        $this->machineA->forceFill(['machine_code' => 'M-A-LIVE', 'machine_name' => 'Mesin A Live'])->save();
        $location->forceFill(['location_name' => 'Line Live'])->save();

        $row = $service->exportRows([])->firstWhere('execution_id', $rowBefore->execution_id);
        $this->assertNotNull($row);

        $view = $this->view('pages.report.pm.pdf', [
            'rows' => collect([$row]),
            'reportPmService' => $service,
            'filters' => [],
            'summary' => ['total' => 1],
            'printedBy' => $this->admin->name,
        ]);

        $view->assertSee('M-A-01');
        $view->assertSee('Mesin A');
        $view->assertSee('Line Produksi A');
        $view->assertDontSee('M-A-LIVE');
        $view->assertDontSee('Mesin A Live');
        $view->assertDontSee('Line Live');
    }

    public function test_report_pm_legacy_snapshot_label_renders_in_html_excel_and_pdf(): void
    {
        $service = app(ReportPmService::class);
        $executionId = (int) $service->exportRows([])->firstWhere('machine_code', 'M-A-01')->execution_id;
        PmExecution::query()->whereKey($executionId)->update([
            'machine_code_snapshot' => null,
            'machine_name_snapshot' => null,
            'location_code_snapshot' => null,
            'location_name_snapshot' => null,
        ]);

        $row = $service->exportRows([])->firstWhere('execution_id', $executionId);
        $this->assertNotNull($row);

        $legacyLabel = 'Data historis tidak tersedia (legacy)';

        // Filter controls intentionally retain live master values for filtering.
        $this->actingAs($this->admin)
            ->get('/report/pm')
            ->assertOk()
            ->assertSee($legacyLabel)
            ->assertDontSee('Mesin A');

        // Excel mapping
        $mapped = (new ReportPmExport(collect([$row]), $service))->map($row);
        $this->assertContains($legacyLabel, $mapped);
        $this->assertNotContains('Mesin A', $mapped);
        $this->assertNotContains('Line Produksi A', $mapped);

        // PDF Blade
        $view = $this->view('pages.report.pm.pdf', [
            'rows' => collect([$row]),
            'reportPmService' => $service,
            'filters' => [],
            'summary' => ['total' => 1],
            'printedBy' => $this->admin->name,
        ]);
        $view->assertSee($legacyLabel);
        $view->assertDontSee('Mesin A');
        $view->assertDontSee('Line Produksi A');
    }

    public function test_report_pm_schedule_only_rows_keep_current_master_identity(): void
    {
        $row = app(ReportPmService::class)->exportRows([])->firstWhere('execution_id', null);

        $this->assertNotNull($row);
        $this->assertSame('M-B-01', $row->machine_code);
        $this->assertSame('Mesin B', $row->machine_name);
        $this->assertSame('Line Produksi B', $row->location_name);
    }

    public function test_admin_can_access_report_pm_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/report/pm');

        $response->assertOk();
        $response->assertSee('Report PM');
        $response->assertSee('M-A-01');
        $response->assertSee('M-B-01');
        $response->assertSee('Scheduled');
    }

    public function test_operator_and_guest_cannot_access_report_pm_page(): void
    {
        $this->actingAs($this->operator)
            ->get('/report/pm')
            ->assertRedirect('/403');

        $guestSession = GuestSession::query()->create([
            'guest_name' => 'Guest PRIME',
            'session_id' => 'guest-session-report',
            'login_at' => now(),
        ]);

        $this->withSession([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ])->get('/report/pm')
            ->assertRedirect('/403');
    }

    public function test_report_pm_filter_by_machine_and_warning(): void
    {
        $response = $this->actingAs($this->admin)->get('/report/pm?machine_id='.$this->machineB->id.'&warning=warning');

        $response->assertOk();
        $response->assertSee('M-B-01');
        $response->assertSee('Menampilkan 1-1 dari total 1 data');
    }

    public function test_report_pm_filter_by_schedule_date_status_and_pic(): void
    {
        $response = $this->actingAs($this->admin)->get('/report/pm?'.http_build_query([
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'status' => 'waiting_review',
            'pic_operator' => $this->operator->name,
        ]));

        $response->assertOk();
        $response->assertSee('M-B-01');
        $response->assertSee('Menampilkan 1-1 dari total 1 data');
    }

    public function test_report_pm_filter_status_scheduled_returns_schedule_without_execution(): void
    {
        $response = $this->actingAs($this->admin)->get('/report/pm?'.http_build_query([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'scheduled',
        ]));

        $response->assertOk();
        $response->assertSee('Scheduled');
        $response->assertSee('M-B-01');
    }

    public function test_report_pm_pagination_limited_to_10_rows(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->createExecutionWithItem(
                machine: $this->machineA,
                scheduledDate: now()->subDays($i + 2)->toDateString(),
                submittedAt: now()->subDays($i + 2)->setTime(8, 0, 0),
                status: 'approved',
                scheduleStatus: 'approved',
                isWarning: false,
                partName: 'Part Pagination '.$i,
            );
        }

        $response = $this->actingAs($this->admin)->get('/report/pm');

        $response->assertOk();
        $response->assertSee('Menampilkan 1-10');
    }

    public function test_report_pm_invalid_date_range_shows_error_message(): void
    {
        $response = $this->actingAs($this->admin)->get('/report/pm?start_date=2026-05-10&end_date=2026-05-01');

        $response->assertSessionHasErrors('end_date');
        $response->assertRedirect();
    }

    public function test_report_pm_export_pdf_and_excel_create_report_exports_records(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/report/pm/export/pdf', [
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Report PM berhasil dibuat.')
            ->assertJsonStructure(['download_url']);

        $this->assertDatabaseHas('report_exports', [
            'report_type' => 'pm',
            'file_type' => 'pdf',
            'status' => 'completed',
            'requested_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson('/report/pm/export/excel', [
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Report PM berhasil dibuat.')
            ->assertJsonStructure(['download_url']);

        $this->assertDatabaseHas('report_exports', [
            'report_type' => 'pm',
            'file_type' => 'excel',
            'status' => 'completed',
            'requested_by' => $this->admin->id,
        ]);
    }

    public function test_report_pm_export_without_data_returns_warning_response(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/report/pm/export/pdf', [
                'start_date' => now()->subYears(3)->toDateString(),
                'end_date' => now()->subYears(3)->addDay()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Tidak ada data PM untuk diexport.');
    }

    public function test_report_pm_export_respects_active_filter(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/report/pm/export/excel', [
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString(),
                'machine_id' => $this->machineB->id,
                'warning' => 'warning',
                'status' => 'waiting_review',
            ])
            ->assertOk();

        $export = ReportExport::query()
            ->where('report_type', 'pm')
            ->where('file_type', 'excel')
            ->latest('id')
            ->first();

        $this->assertNotNull($export);
        $this->assertSame($this->machineB->id, $export->filter_data['machine_id']);
        $this->assertSame('warning', $export->filter_data['warning']);
        $this->assertSame('waiting_review', $export->filter_data['status']);
    }

    public function test_report_pm_export_includes_operator_note(): void
    {
        $service = app(ReportPmService::class);
        $rows = $service->exportRows([
            'machine_id' => $this->machineB->id,
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $row = $rows->firstWhere('machine_code', 'M-B-01');

        $this->assertNotNull($row);
        $this->assertSame('Perlu dicek ulang di shift malam', $row->operator_note);

        $export = new ReportPmExport(collect([$row]), $service);

        $this->assertContains('Catatan', $export->headings());
        $this->assertContains('Perlu dicek ulang di shift malam', $export->map($row));

        $view = $this->view('pages.report.pm.pdf', [
            'rows' => collect([$row]),
            'reportPmService' => $service,
            'filters' => [],
            'summary' => ['total' => 1],
            'printedBy' => $this->admin->name,
        ]);

        $view->assertSee('Catatan');
        $view->assertSee('Perlu dicek ulang di shift malam');
    }

    public function test_report_pm_export_failed_marks_report_export_failed(): void
    {
        Pdf::shouldReceive('loadView')
            ->once()
            ->andThrow(new \RuntimeException('Mock PDF gagal'));

        $this->actingAs($this->admin)
            ->postJson('/report/pm/export/pdf', [
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString(),
            ])
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Report PM gagal dibuat. Silakan coba kembali.');

        $this->assertDatabaseHas('report_exports', [
            'report_type' => 'pm',
            'file_type' => 'pdf',
            'status' => 'failed',
            'requested_by' => $this->admin->id,
        ]);
    }

    protected function createExecutionWithItem(
        Machine $machine,
        string $scheduledDate,
        Carbon $submittedAt,
        string $status,
        string $scheduleStatus,
        bool $isWarning,
        string $partName,
        ?string $note = null,
    ): void {
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'CHK-'.$machine->machine_code.'-'.str_replace('-', '', $scheduledDate).'-'.substr((string) str()->uuid(), 0, 6),
            'checksheet_name' => 'Checksheet '.$machine->machine_name,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $checksheetMachine = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $machine->id,
            'created_by' => $this->admin->id,
        ]);

        $schedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $checksheetMachine->id,
            'frequency_type' => 'daily',
            'start_date' => $scheduledDate,
            'generate_until' => now()->addMonth()->toDateString(),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $scheduleDate = PmScheduleDate::query()->create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $machine->id,
            'scheduled_date' => $scheduledDate,
            'status' => $scheduleStatus,
            'status_changed_at' => now(),
        ]);

        $execution = PmExecution::query()->create([
            // Slice A: snapshot identitas transaksi.
            'machine_code_snapshot' => $machine->machine_code,
            'machine_name_snapshot' => $machine->machine_name,
            'location_code_snapshot' => Location::query()->findOrFail($machine->location_id)->location_code,
            'location_name_snapshot' => Location::query()->findOrFail($machine->location_id)->location_name,
            'pm_schedule_date_id' => $scheduleDate->id,
            'machine_id' => $machine->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => $status,
            'started_at' => $submittedAt->copy()->subMinutes(30),
            'submitted_at' => $submittedAt,
            'approved_at' => $status === 'approved' ? $submittedAt->copy()->addHours(2) : null,
            'approved_by' => $status === 'approved' ? $this->admin->id : null,
            'approved_by_name_snapshot' => $status === 'approved' ? $this->admin->name : null,
        ]);

        PmExecutionItem::query()->create([
            'pm_execution_id' => $execution->id,
            'part_name_snapshot' => $partName,
            'standard_name_snapshot' => 'Standard '.$partName,
            'input_type_snapshot' => 'number',
            'number_value' => $isWarning ? 90 : 45,
            'min_value_snapshot' => 10,
            'max_value_snapshot' => 80,
            'unit_snapshot' => 'C',
            'is_warning' => $isWarning,
            'warning_message' => $isWarning ? 'Warning value' : null,
            'note' => $note,
        ]);
    }

    protected function createScheduleOnly(Machine $machine, string $scheduledDate, string $status): void
    {
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'CHK-SCHEDULE-'.$machine->machine_code.'-'.str_replace('-', '', $scheduledDate),
            'checksheet_name' => 'Checksheet '.$machine->machine_name,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $checksheetMachine = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $machine->id,
            'created_by' => $this->admin->id,
        ]);

        $schedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $checksheetMachine->id,
            'frequency_type' => 'daily',
            'start_date' => $scheduledDate,
            'generate_until' => now()->addMonth()->toDateString(),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        PmScheduleDate::query()->create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $machine->id,
            'scheduled_date' => $scheduledDate,
            'status' => $status,
            'status_changed_at' => now(),
        ]);
    }
}
