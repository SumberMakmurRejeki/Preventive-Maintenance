<?php

namespace Tests\Feature\Feature\PM;

use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmSchedule;
use App\Models\UserActivityLog;
use App\Services\PM\InvalidMachineTransitionException;
use App\Services\PM\MachineRecommissionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Menguji seam recommission explicit agar era ENDED tetap menjadi histori.
 */
class MachineRecommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private Machine $machine;

    private PmChecksheetMachine $assignment;

    private PmSchedule $endedSchedule;

    protected function setUp(): void
    {
        parent::setUp();
        $request = Request::create('/pm/test', 'POST');
        $request->setLaravelSession(app('session.store'));
        $this->app->instance('request', $request);
        $location = Location::query()->create(['location_code' => 'LOC-MRC', 'location_name' => 'Recommission Location', 'is_active' => true]);
        $this->machine = Machine::query()->create(['location_id' => $location->id, 'machine_code' => 'MC-MRC', 'machine_name' => 'Recommission Machine', 'qr_token' => 'qr-mrc', 'is_active' => false, 'lifecycle_status' => 'retired']);
        $checksheet = PmChecksheet::query()->create(['checksheet_code' => 'PM-MRC', 'checksheet_name' => 'Recommission Checksheet', 'is_active' => true]);
        $this->assignment = PmChecksheetMachine::query()->create(['pm_checksheet_id' => $checksheet->id, 'machine_id' => $this->machine->id]);
        $this->endedSchedule = PmSchedule::query()->create(['pm_checksheet_machine_id' => $this->assignment->id, 'frequency_type' => 'daily', 'operational_from' => '2026-09-01', 'start_date' => '2026-09-01', 'generate_until' => '2026-09-05', 'is_active' => false, 'lifecycle_status' => 'ended']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Recommission membuat era baru tanpa mengubah era ENDED sebelumnya.
     */
    public function test_retired_machine_recommission_creates_one_new_active_era(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 09:00:00', 'Asia/Jakarta'));
        app(MachineRecommissionService::class)->recommission($this->assignment, $this->configuration(), 'Mesin selesai diremajakan');

        $this->assertSame('ended', $this->endedSchedule->fresh()->lifecycle_status);
        $this->assertSame('active', $this->machine->fresh()->lifecycle_status);
        $newSchedule = PmSchedule::query()->currentEra()->sole();
        $this->assertSame($this->assignment->id, $newSchedule->pm_checksheet_machine_id);
        $this->assertSame('2026-09-15', $newSchedule->effective_live_from?->toDateString());
    }

    /**
     * Retry setelah sukses wajib gagal tertutup karena current era sudah ada.
     */
    public function test_retry_after_success_fails_closed(): void
    {
        app(MachineRecommissionService::class)->recommission($this->assignment, $this->configuration(), 'Recommission pertama');

        $this->assertThrows(
            fn () => app(MachineRecommissionService::class)->recommission($this->assignment, $this->configuration(), 'Retry recommission'),
            InvalidMachineTransitionException::class,
        );
    }

    /**
     * Recommission hanya menyentuh assignment target; sibling pada checksheet
     * yang sama dan histori ENDED sibling tidak boleh berubah.
     */
    public function test_recommission_is_isolated_to_target_assignment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 09:00:00', 'Asia/Jakarta'));

        // Sibling dormant pada checksheet yang sama dengan histori ENDED sendiri.
        $siblingMachine = Machine::query()->create(['location_id' => $this->machine->location_id, 'machine_code' => 'MC-MRC-SIB', 'machine_name' => 'Sibling Machine', 'qr_token' => 'qr-mrc-sib', 'is_active' => false, 'lifecycle_status' => 'retired']);
        $siblingChecksheetId = $this->assignment->pm_checksheet_id;
        $siblingAssignment = PmChecksheetMachine::query()->create(['pm_checksheet_id' => $siblingChecksheetId, 'machine_id' => $siblingMachine->id]);
        $siblingEnded = PmSchedule::query()->create(['pm_checksheet_machine_id' => $siblingAssignment->id, 'frequency_type' => 'daily', 'operational_from' => '2026-08-01', 'start_date' => '2026-08-01', 'generate_until' => '2026-08-05', 'is_active' => false, 'lifecycle_status' => 'ended']);

        app(MachineRecommissionService::class)->recommission($this->assignment, $this->configuration(), 'Recommission target saja');

        // Target mendapat era baru; sibling tidak berubah sama sekali.
        $this->assertSame('active', $this->machine->fresh()->lifecycle_status);
        $this->assertSame(2, PmSchedule::query()->where('pm_checksheet_machine_id', $this->assignment->id)->count());
        $this->assertSame('retired', $siblingMachine->fresh()->lifecycle_status);
        $this->assertSame(1, PmSchedule::query()->where('pm_checksheet_machine_id', $siblingAssignment->id)->count());
        $this->assertSame('ended', $siblingEnded->fresh()->lifecycle_status);
        $this->assertNull(PmSchedule::query()->where('pm_checksheet_machine_id', $siblingAssignment->id)->where('lifecycle_status', 'active')->first());
    }

    /**
     * TASK-006 Slice C (Correction 8): audit recommission wajib merekam state
     * Machine SEBELUM mutasi. Input RETIRED harus tercatat sebagai retired,
     * bukan state yang diasumsikan dari alur transisi.
     */
    public function test_recommission_audit_records_retired_old_state_accurately(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 09:00:00', 'Asia/Jakarta'));

        app(MachineRecommissionService::class)->recommission($this->assignment, $this->configuration(), 'Recommission dengan audit retired');

        $log = $this->latestRecommissionLog();
        $this->assertNotNull($log, 'Recommission harus menulis audit meski tanpa perubahan lifecycle Machine.');

        // Old state: benar-benar retired sebelum promosi.
        $this->assertSame('retired', $log->old_values['lifecycle_status']);
        $this->assertFalse((bool) $log->old_values['is_active']);
        $this->assertNull($log->old_values['effective_live_from']);

        // New state: hasil promosi yang nyata, bukan klaim tetap.
        $this->assertSame('active', $log->new_values['lifecycle_status']);
        $this->assertTrue((bool) $log->new_values['is_active']);
        // Audit menyimpan nilai bertimezone; bandingkan pada zona bisnis PRIME.
        $this->assertSame(
            '2026-09-15',
            Carbon::parse($log->new_values['effective_live_from'])->setTimezone('Asia/Jakarta')->toDateString(),
        );
        // Baris Machine sendiri harus menyimpan boundary tanggal bisnis yang sama.
        $this->assertSame('2026-09-15', $this->machine->fresh()->effective_live_from?->toDateString());
        $this->assertSame(
            PmSchedule::query()->currentEra()->where('pm_checksheet_machine_id', $this->assignment->id)->value('id'),
            $log->new_values['pm_schedule_id'],
        );
    }

    /**
     * Recommission assignment dormant saat Machine sudah ACTIVE tidak boleh
     * mengklaim transisi retired -> active, dan boundary effective_live_from
     * Machine yang sudah active harus tetap tidak berubah.
     */
    public function test_recommission_audit_records_already_active_state_without_boundary_change(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 09:00:00', 'Asia/Jakarta'));

        // Machine sudah ACTIVE dengan boundary live yang sudah mapan.
        $this->machine->forceFill([
            'lifecycle_status' => 'active',
            'is_active' => true,
            'effective_live_from' => '2026-09-01',
        ])->save();

        // Assignment dorman kedua pada checksheet yang sama (era ENDED sendiri).
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-MRC-DORMANT',
            'checksheet_name' => 'Dormant Recommission Checksheet',
            'is_active' => true,
        ]);
        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);
        $ended = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'daily',
            'operational_from' => '2026-08-01',
            'start_date' => '2026-08-01',
            'generate_until' => '2026-08-05',
            'is_active' => false,
            'lifecycle_status' => 'ended',
        ]);

        app(MachineRecommissionService::class)->recommission($assignment, $this->configuration(), 'Recommission assignment dorman pada Machine aktif');

        $log = $this->latestRecommissionLog();
        $this->assertNotNull($log);

        // Tidak boleh ada klaim transisi retired yang tidak pernah terjadi.
        $this->assertSame('active', $log->old_values['lifecycle_status']);
        $this->assertTrue((bool) $log->old_values['is_active']);
        $this->assertSame('active', $log->new_values['lifecycle_status']);

        // Boundary live Machine tidak berubah, baik pada audit maupun pada baris.
        // Audit menyimpan nilai bertimezone; bandingkan pada zona bisnis PRIME.
        $this->assertSame('2026-09-01', Carbon::parse($log->old_values['effective_live_from'])->setTimezone('Asia/Jakarta')->toDateString());
        $this->assertSame('2026-09-01', Carbon::parse($log->new_values['effective_live_from'])->setTimezone('Asia/Jakarta')->toDateString());
        $this->assertSame('2026-09-01', $this->machine->fresh()->effective_live_from?->toDateString());

        // Histori ENDED tetap terminal dan hanya era baru yang aktif.
        $this->assertSame('ended', $ended->fresh()->lifecycle_status);
        $this->assertSame(
            1,
            PmSchedule::query()->currentEra()->where('pm_checksheet_machine_id', $assignment->id)->count(),
        );
    }

    /**
     * Audit recommission terakhir untuk Machine target pada test ini.
     */
    private function latestRecommissionLog(): ?UserActivityLog
    {
        return UserActivityLog::query()
            ->where('module_name', 'master_mesin')
            ->where('action', 'recommission')
            ->where('record_id', $this->machine->id)
            ->orderByDesc('id')
            ->first();
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        return ['frequency_type' => 'daily', 'operational_from' => '2026-09-15', 'start_date' => '2026-09-15', 'generate_until' => '2026-09-20'];
    }
}
