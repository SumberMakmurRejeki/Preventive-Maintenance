<?php

namespace Tests\Feature\Feature\PM;

use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menguji backstop database untuk satu current Schedule era per assignment.
 */
class CurrentEraSchemaTest extends TestCase
{
    use RefreshDatabase;

    private PmChecksheetMachine $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $location = Location::query()->create(['location_code' => 'LOC-CES', 'location_name' => 'Current Era Location', 'is_active' => true]);
        $machine = Machine::query()->create(['location_id' => $location->id, 'machine_code' => 'MC-CES', 'machine_name' => 'Current Era Machine', 'qr_token' => 'qr-ces', 'is_active' => true, 'lifecycle_status' => 'active']);
        $checksheet = PmChecksheet::query()->create(['checksheet_code' => 'PM-CES', 'checksheet_name' => 'Current Era Checksheet', 'is_active' => true]);
        $this->assignment = PmChecksheetMachine::query()->create(['pm_checksheet_id' => $checksheet->id, 'machine_id' => $machine->id]);
    }

    /**
     * ACTIVE dan PAUSED tidak boleh hidup bersamaan dalam satu assignment.
     */
    public function test_database_rejects_second_non_terminal_era(): void
    {
        $this->createSchedule('active');

        $this->expectException(UniqueConstraintViolationException::class);
        $this->createSchedule('paused');
    }

    /**
     * Era terminal dapat disimpan berulang dan tidak memblokir era baru aktif.
     */
    public function test_ended_history_and_current_era_can_coexist(): void
    {
        $this->createSchedule('ended');
        $this->createSchedule('ended');
        $current = $this->createSchedule('active');

        $this->assertSame(2, PmSchedule::query()->where('pm_checksheet_machine_id', $this->assignment->id)->where('lifecycle_status', 'ended')->count());
        $this->assertSame($current->id, PmSchedule::query()->currentEra()->sole()->id);
    }

    /**
     * Kegagalan duplicate era harus ter-scope: era existing, occurrence-nya,
     * dan era current sibling tidak boleh ikut termutasi.
     */
    public function test_duplicate_current_era_failure_leaves_existing_state_untouched(): void
    {
        $existing = $this->createSchedule('active');
        $existingDate = PmScheduleDate::query()->create([
            'pm_schedule_id' => $existing->id,
            'machine_id' => $this->assignment->machine_id,
            'scheduled_date' => '2026-09-02',
            'status' => 'scheduled',
        ]);

        // Sibling assignment dengan era current sendiri.
        $siblingMachine = Machine::query()->create(['location_id' => $this->assignment->machine->location_id, 'machine_code' => 'MC-CES-SIB', 'machine_name' => 'Sibling Machine', 'qr_token' => 'qr-ces-sib', 'is_active' => true, 'lifecycle_status' => 'active']);
        $siblingAssignment = PmChecksheetMachine::query()->create(['pm_checksheet_id' => $this->assignment->pm_checksheet_id, 'machine_id' => $siblingMachine->id]);
        $siblingSchedule = PmSchedule::query()->create(['pm_checksheet_machine_id' => $siblingAssignment->id, 'frequency_type' => 'daily', 'operational_from' => '2026-09-01', 'start_date' => '2026-09-01', 'generate_until' => '2026-10-01', 'is_active' => true, 'lifecycle_status' => 'active']);

        // Percobaan membuat era current kedua harus ditolak database.
        try {
            $this->createSchedule('paused');
            $this->fail('Database harus menolak era current kedua untuk assignment yang sama.');
        } catch (UniqueConstraintViolationException) {
            // Diharapkan: invariant dijaga pada level database.
        }

        $this->assertSame('active', $existing->fresh()->lifecycle_status);
        $this->assertSame('scheduled', $existingDate->fresh()->status);
        $this->assertSame('active', $siblingSchedule->fresh()->lifecycle_status);
        $this->assertSame(1, PmSchedule::query()->where('pm_checksheet_machine_id', $this->assignment->id)->count());
        $this->assertSame($siblingSchedule->id, PmSchedule::query()->currentEra()->where('pm_checksheet_machine_id', $siblingAssignment->id)->sole()->id);
    }

    /**
     * Baris non-terminal yang sudah soft-deleted tidak memblokir era current baru.
     */
    public function test_soft_deleted_non_terminal_row_does_not_block_new_current_era(): void
    {
        $softDeleted = $this->createSchedule('paused');
        $softDeleted->delete();

        $current = $this->createSchedule('active');

        $this->assertSame($current->id, PmSchedule::query()->currentEra()->sole()->id);
        $this->assertSame(0, PmSchedule::query()->currentEra()->where('id', $softDeleted->id)->count());
    }

    private function createSchedule(string $status): PmSchedule
    {
        return PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $this->assignment->id,
            'frequency_type' => 'daily',
            'operational_from' => '2026-09-01',
            'start_date' => '2026-09-01',
            'generate_until' => '2026-10-01',
            'is_active' => $status === 'active',
            'lifecycle_status' => $status,
        ]);
    }
}
