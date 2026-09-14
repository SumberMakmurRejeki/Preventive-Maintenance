<?php

namespace Tests\Feature\Feature\PM;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Bukti migration additive lifecycle pada database test yang diisolasi.
 * Test ini tidak menyentuh database produksi: RefreshDatabase memakai sqlite
 * :memory: (phpunit.xml) atau mysql/db_preventive_maintenance_test, dan test
 * hanya menguji schema hasil migrasi tanpa menjalankan DDL apa pun.
 */
class LifecycleSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * RefreshDatabase sudah menyediakan schema hasil migrasi; test ini hanya
     * menguji kontrak yang telah termaterialisasi, bukan menjalankan DDL.
     */
    private function currentLifecycleColumns(string $table): array
    {
        $columns = collect(Schema::getColumns($table))->keyBy('name')->all();

        // SQLite merepresentasikan default string dengan kutip; dinormalisasi
        // agar kontrak default 'active' sama antara MySQL dan SQLite.
        $columns['lifecycle_status']['default'] = trim((string) $columns['lifecycle_status']['default'], "'");

        return $columns;
    }

    /**
     * Menyiapkan relasi legacy minimal: lokasi, machine, checksheet, assignment, schedule.
     *
     * @return array{machine_active: int, machine_inactive: int, schedule_active: int, schedule_inactive: int}
     */
    private function seedLegacyRows(): array
    {
        $now = now()->toDateTimeString();

        $locationId = DB::table('locations')->insertGetId([
            'location_code' => 'LOC-LC',
            'location_name' => 'Lifecycle Line',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $machineActiveId = DB::table('machines')->insertGetId([
            'location_id' => $locationId,
            'machine_code' => 'MC-LC-A',
            'machine_name' => 'Legacy Active Machine',
            'qr_token' => 'qr-lc-a',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $machineInactiveId = DB::table('machines')->insertGetId([
            'location_id' => $locationId,
            'machine_code' => 'MC-LC-I',
            'machine_name' => 'Legacy Inactive Machine',
            'qr_token' => 'qr-lc-i',
            'is_active' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $checksheetId = DB::table('pm_checksheets')->insertGetId([
            'checksheet_code' => 'PM-LC-001',
            'checksheet_name' => 'Lifecycle Checksheet',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $assignmentIds = [];
        foreach ([$machineActiveId, $machineInactiveId] as $machineId) {
            $assignmentIds[$machineId] = DB::table('pm_checksheet_machines')->insertGetId([
                'pm_checksheet_id' => $checksheetId,
                'machine_id' => $machineId,
                'assigned_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $scheduleActiveId = DB::table('pm_schedules')->insertGetId([
            'pm_checksheet_machine_id' => $assignmentIds[$machineActiveId],
            'frequency_type' => 'daily',
            'start_date' => '2026-09-01',
            'generate_until' => '2027-09-01',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $scheduleInactiveId = DB::table('pm_schedules')->insertGetId([
            'pm_checksheet_machine_id' => $assignmentIds[$machineInactiveId],
            'frequency_type' => 'daily',
            'start_date' => '2026-09-01',
            'generate_until' => '2027-09-01',
            'is_active' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'machine_active' => $machineActiveId,
            'machine_inactive' => $machineInactiveId,
            'schedule_active' => $scheduleActiveId,
            'schedule_inactive' => $scheduleInactiveId,
        ];
    }

    /**
     * Bukti kontrak finite-state database untuk kolom lifecycle_status.
     *
     * Nilai yang terdaftar harus dapat ditulis langsung ke database, sedangkan
     * nilai di luar daftar wajib ditolak oleh constraint database (ENUM MySQL
     * atau CHECK constraint SQLite), bukan hanya oleh validasi aplikasi.
     */
    public function test_lifecycle_status_database_rejects_values_outside_finite_state_contract(): void
    {
        $ids = $this->seedLegacyRows();
        $now = now()->toDateTimeString();
        $locationId = DB::table('machines')->where('id', $ids['machine_active'])->value('location_id');
        $assignmentId = DB::table('pm_schedules')->where('id', $ids['schedule_active'])->value('pm_checksheet_machine_id');

        foreach (['active', 'inactive', 'retired'] as $state) {
            DB::table('machines')->insert([
                'location_id' => $locationId,
                'machine_code' => 'MC-FS-'.$state,
                'machine_name' => 'Finite State '.$state,
                'qr_token' => 'qr-fs-'.$state,
                'is_active' => true,
                'lifecycle_status' => $state,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (['active', 'paused', 'ended'] as $state) {
            DB::table('pm_schedules')->insert([
                'pm_checksheet_machine_id' => $assignmentId,
                'frequency_type' => 'daily',
                'start_date' => '2026-09-01',
                'generate_until' => '2027-09-01',
                'is_active' => true,
                'lifecycle_status' => $state,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Direct invalid persistence must fail at the database boundary.
        $this->assertThrows(function () use ($locationId, $now): void {
            DB::table('machines')->insert([
                'location_id' => $locationId,
                'machine_code' => 'MC-FS-INVALID',
                'machine_name' => 'Finite State Invalid',
                'qr_token' => 'qr-fs-invalid',
                'is_active' => true,
                'lifecycle_status' => 'arbitrary',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, QueryException::class, 'lifecycle_status');

        $this->assertThrows(function () use ($assignmentId, $now): void {
            DB::table('pm_schedules')->insert([
                'pm_checksheet_machine_id' => $assignmentId,
                'frequency_type' => 'daily',
                'start_date' => '2026-09-01',
                'generate_until' => '2027-09-01',
                'is_active' => true,
                'lifecycle_status' => 'arbitrary',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, QueryException::class, 'lifecycle_status');
    }

    /**
     * Snapshot seluruh kolom protected table untuk memastikan assertion
     * schema tidak mengubah histori PM.
     *
     * @param  list<string>  $columns
     */
    private function tableSnapshot(string $table, array $columns): array
    {
        return DB::table($table)
            ->orderBy('id')
            ->get($columns)
            ->map(static fn ($row): array => array_map(
                static fn ($value): ?string => $value === null ? null : (string) $value,
                (array) $row,
            ))
            ->all();
    }

    /*
     * Jaminan khusus migrasi (rollback, reapply, backfill legacy, dan
     * pelestarian histori melalui DDL) tidak diduplikasi di sini: seluruhnya
     * sudah dibuktikan oleh isolated guarded MySQL harness
     * (tests/Support/SLICE-A/lifecycle-finite-state-proof.php mode prove).
     */
    /**
     * Bukti bentuk schema hasil migrasi: kolom lifecycle termaterialisasi
     * dengan tipe finite-state (ENUM pada MySQL, varchar + CHECK pada SQLite).
     */
    public function test_migrated_schema_materializes_finite_state_lifecycle_columns(): void
    {
        $expectedType = DB::connection()->getDriverName() === 'mysql' ? 'enum' : 'varchar';

        foreach (['machines', 'pm_schedules'] as $table) {
            $columns = $this->currentLifecycleColumns($table);

            $this->assertSame($expectedType, $columns['lifecycle_status']['type']);
            $this->assertFalse((bool) $columns['lifecycle_status']['nullable']);
            $this->assertSame('active', $columns['lifecycle_status']['default']);
            $this->assertSame('date', $columns['effective_live_from']['type']);
            $this->assertTrue((bool) $columns['effective_live_from']['nullable']);
            $this->assertNull($columns['effective_live_from']['default']);
        }
    }
}
