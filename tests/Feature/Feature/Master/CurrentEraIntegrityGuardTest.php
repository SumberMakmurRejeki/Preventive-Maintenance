<?php

namespace Tests\Feature\Feature\Master;

use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Services\Master\PmChecksheetService;
use App\Services\Master\PmScheduleUpdateService;
use App\Services\PM\BusinessDate;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Guard integritas "satu era current per assignment" (TASK-006 Slice C).
 *
 * State duplicate-era TIDAK dapat direpresentasikan pada database yang sudah
 * memakai backstop unique current_era_key. Untuk membuktikan guard aplikasi
 * tetap fail-closed (bukan fatal class-not-found) tanpa melemahkan invariant,
 * test ini memakai koneksi SQLite terisolasi yang dimiliki penuh oleh kelas
 * ini dan mensimulasikan deployment SEBELUM backstop diterapkan:
 *
 * - koneksi in-memory dibuat dan dimigrasi penuh pada setUp;
 * - HANYA pada koneksi isolasi ini unique index backstop dilepas;
 * - koneksi utama test suite tidak pernah disentuh;
 * - teardown memulihkan default connection dan membuang koneksi isolasi.
 */
class CurrentEraIntegrityGuardTest extends TestCase
{
    private const ISOLATED_CONNECTION = 'slice_c_duplicate_era';

    protected function setUp(): void
    {
        parent::setUp();

        // Tanggal bisnis deterministik agar payload preview/apply konsisten.
        Carbon::setTestNow(Carbon::parse('2026-06-15 00:00:00', 'Asia/Jakarta'));

        // Koneksi isolasi: in-memory, dimigrasi penuh, lalu dilepas index
        // backstop-nya untuk mensimulasikan state duplicate-era yang sah
        // terjadi pada deployment pra-backstop.
        config()->set('database.connections.'.self::ISOLATED_CONNECTION, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::setDefaultConnection(self::ISOLATED_CONNECTION);
        DB::purge(self::ISOLATED_CONNECTION);
        Artisan::call('migrate', [
            '--database' => self::ISOLATED_CONNECTION,
            '--force' => true,
        ]);
        DB::connection(self::ISOLATED_CONNECTION)->statement(
            'DROP INDEX IF EXISTS pm_schedules_current_era_unique'
        );
    }

    protected function tearDown(): void
    {
        // Kembalikan default connection SEBELUM teardown lain berjalan agar
        // koneksi utama suite tidak pernah tertukar dengan koneksi isolasi.
        DB::setDefaultConnection('sqlite');
        DB::purge(self::ISOLATED_CONNECTION);
        config()->offsetUnset('database.connections.'.self::ISOLATED_CONNECTION);
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Preview harus fail-closed melalui jalur validasi (ValidationException),
     * bukan fatal class-not-found, ketika state duplicate-era terdeteksi.
     */
    public function test_preview_fails_closed_with_validation_error_on_duplicate_current_era(): void
    {
        $checksheet = $this->makeChecksheetWithDuplicateCurrentEra();

        $this->assertThrows(
            fn () => app(PmScheduleUpdateService::class)->preview($checksheet, $this->schedulePayload()),
            ValidationException::class,
        );

        // Fail-closed: state tidak berubah dan tidak ada era baru yang dibuat.
        $this->assertSame(2, PmSchedule::query()->count());
    }

    /**
     * Apply harus menolak duplicate-era lewat jalur validasi yang sama, tanpa
     * mutasi parsial: transaksi di-rollback sehingga tanggal tidak tercipta.
     */
    public function test_apply_fails_closed_without_partial_mutation_on_duplicate_current_era(): void
    {
        $checksheet = $this->makeChecksheetWithDuplicateCurrentEra();
        $service = app(PmScheduleUpdateService::class);
        $payload = $this->schedulePayload();
        $token = $service->fingerprint($checksheet, BusinessDate::today(), $payload);

        $this->assertThrows(
            fn () => $service->apply($checksheet, $payload, $token),
            ValidationException::class,
        );

        // Tidak ada mutasi parsial: jumlah schedule dan tanggal tetap.
        $this->assertSame(2, PmSchedule::query()->count());
        $this->assertSame(0, PmScheduleDate::query()->withTrashed()->count());
    }

    /**
     * Sibling berikutnya yang invalid tetap harus memicu kegagalan integritas
     * meskipun assignment pertama sudah mengisi payload Schedule bersama.
     */
    public function test_duplicate_current_era_on_later_sibling_is_not_skipped(): void
    {
        // Assignment A sah: satu era ACTIVE.
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-ERA-SIB',
            'checksheet_name' => 'Sibling Order Guard Checksheet',
            'is_active' => true,
        ]);
        $machineA = $this->makeMachine('MC-ERA-A');
        $assignmentA = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $machineA->id,
        ]);
        PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignmentA->id,
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-01',
            'start_date' => '2026-06-01',
            'generate_until' => '2026-09-01',
            'is_active' => true,
            'lifecycle_status' => 'active',
        ]);

        // Assignment B invalid: ACTIVE plus PAUSED current era.
        $machineB = $this->makeMachine('MC-ERA-B');
        $assignmentB = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $machineB->id,
        ]);
        $this->seedCurrentEraPair($assignmentB->id);

        $this->assertThrows(
            fn () => app(PmChecksheetService::class)->toWizardPayload($checksheet),
            DomainException::class,
        );
    }

    /**
     * Urutan sibling dibalik: duplicate pada assignment pertama juga harus
     * fail-closed, membuktikan guard tidak bergantung urutan sibling.
     */
    public function test_duplicate_current_era_on_first_sibling_is_not_skipped(): void
    {
        // Assignment pertama langsung invalid: ACTIVE plus PAUSED current era.
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-ERA-FIRST',
            'checksheet_name' => 'First Sibling Guard Checksheet',
            'is_active' => true,
        ]);
        $machineA = $this->makeMachine('MC-ERA-C');
        $assignmentA = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $machineA->id,
        ]);
        $this->seedCurrentEraPair($assignmentA->id);

        // Assignment kedua sah: satu era ACTIVE.
        $machineB = $this->makeMachine('MC-ERA-D');
        $assignmentB = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $machineB->id,
        ]);
        PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignmentB->id,
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-01',
            'start_date' => '2026-06-01',
            'generate_until' => '2026-09-01',
            'is_active' => true,
            'lifecycle_status' => 'active',
        ]);

        $this->assertThrows(
            fn () => app(PmChecksheetService::class)->toWizardPayload($checksheet),
            DomainException::class,
        );
    }

    /**
     * Buat checksheet dengan SATU assignment yang mempunyai dua era current.
     */
    private function makeChecksheetWithDuplicateCurrentEra(): PmChecksheet
    {
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-ERA-DUP',
            'checksheet_name' => 'Duplicate Era Guard Checksheet',
            'is_active' => true,
        ]);
        $machine = $this->makeMachine('MC-ERA-DUP');
        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $machine->id,
        ]);

        $this->seedCurrentEraPair($assignment->id);

        return $checksheet;
    }

    /**
     * Seed dua era current (ACTIVE + PAUSED) untuk satu assignment.
     */
    private function seedCurrentEraPair(int $assignmentId): void
    {
        PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignmentId,
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-01',
            'start_date' => '2026-06-01',
            'generate_until' => '2026-09-01',
            'is_active' => true,
            'lifecycle_status' => 'active',
        ]);
        PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignmentId,
            'frequency_type' => 'weekly',
            'weekly_days' => [1, 4],
            'operational_from' => '2026-06-02',
            'start_date' => '2026-06-02',
            'generate_until' => '2026-09-02',
            'is_active' => false,
            'lifecycle_status' => 'paused',
        ]);
    }

    private function makeMachine(string $code): Machine
    {
        // Location dibuat per pemanggilan agar FK machines.location_id (non-null) sah.
        $location = Location::query()->create([
            'location_code' => 'LOC-'.strtoupper($code),
            'location_name' => "Location {$code}",
            'is_active' => true,
        ]);

        return Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => $code,
            'machine_name' => "Machine {$code}",
            'qr_token' => 'qr-'.strtolower($code),
            'is_active' => true,
            'lifecycle_status' => 'active',
        ]);
    }

    /**
     * Payload schedule wizard standar untuk guard ini.
     *
     * @return array<string, mixed>
     */
    private function schedulePayload(): array
    {
        return [
            'frequency_type' => 'weekly',
            'weekly_days' => [2, 5],
            'monthly_day' => null,
            'operational_from' => '2026-06-15',
            'start_date' => '2026-06-15',
            'generate_until' => '2026-09-15',
        ];
    }
}
