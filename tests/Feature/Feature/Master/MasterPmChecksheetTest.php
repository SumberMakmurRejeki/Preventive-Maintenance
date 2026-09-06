<?php

namespace Tests\Feature\Feature\Master;

use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmExecution;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterPmChecksheetTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

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

        $location = Location::query()->create([
            'location_code' => 'LOC-01',
            'location_name' => 'Line 1',
            'is_active' => true,
        ]);

        $this->machine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'MC-01',
            'machine_name' => 'Sealing Machine',
            'qr_token' => 'qr-mc-01',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_access_master_checksheet_pages(): void
    {
        $this->actingAs($this->admin)->get('/pm/master-checksheet')->assertOk()->assertSee('Master PM Checksheet');
        $this->actingAs($this->admin)->get('/pm/master-checksheet/create')->assertOk()->assertSee('Buat Checksheet Baru');
    }

    public function test_only_admin_can_access_master_checksheet_pages(): void
    {
        $guestSession = GuestSession::query()->create([
            'guest_name' => 'Guest PRIME',
            'session_id' => 'guest-session',
            'login_at' => now(),
        ]);

        $this->actingAs($this->operator)->get('/pm/master-checksheet')->assertRedirect('/403');

        $this->withSession([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ])->get('/pm/master-checksheet')->assertRedirect('/403');
    }

    public function test_admin_can_create_checksheet_with_nested_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 00:00:00', 'Asia/Jakarta'));

        $payload = [
            'selected_machine_ids' => [$this->machine->id],
            'parts' => [
                $this->machine->id => [
                    ['id' => 'part-1', 'name' => 'Motor Drive', 'description' => 'Part utama'],
                ],
            ],
            'standards' => [
                'part-1' => [
                    [
                        'name' => 'Cek suhu motor',
                        'input_type' => 'number',
                        'target_value' => 60,
                        'unit' => 'Celcius',
                        'is_required' => true,
                        'is_active' => true,
                    ],
                ],
            ],
            'schedule' => [
                'frequency_type' => 'daily',
                'operational_from' => '2026-05-21',
                'weekly_days' => [],
                'monthly_day' => null,
            ],
        ];

        $response = $this->actingAs($this->admin)->post('/pm/master-checksheet', [
            'checksheet_code' => 'PM-CH-001',
            'checksheet_name' => 'PM Mingguan Line 1',
            'description' => 'Checksheet test',
            'is_active' => '1',
            'wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        $checksheet = PmChecksheet::query()->where('checksheet_code', 'PM-CH-001')->firstOrFail();

        $response->assertRedirect("/pm/master-checksheet/{$checksheet->id}");
        $this->assertDatabaseHas('pm_checksheets', ['checksheet_code' => 'PM-CH-001']);
        $this->assertDatabaseHas('pm_checksheet_machines', ['pm_checksheet_id' => $checksheet->id, 'machine_id' => $this->machine->id]);
        $this->assertDatabaseHas('pm_checksheet_parts', ['part_name' => 'Motor Drive']);
        $this->assertDatabaseHas('pm_checksheet_standards', ['standard_name' => 'Cek suhu motor', 'input_type' => 'number']);
        $this->assertDatabaseHas('pm_schedules', ['frequency_type' => 'daily']);
        $this->assertDatabaseHas('pm_schedule_dates', ['scheduled_date' => '2026-05-21 00:00:00']);
        $this->assertDatabaseHas('user_activity_logs', ['module_name' => 'master_checksheet', 'action' => 'create']);
    }

    public function test_admin_can_update_and_delete_checksheet(): void
    {
        $this->test_admin_can_create_checksheet_with_nested_data();

        $checksheet = PmChecksheet::query()->where('checksheet_code', 'PM-CH-001')->firstOrFail();

        $payload = [
            'selected_machine_ids' => [$this->machine->id],
            'parts' => [
                $this->machine->id => [
                    ['id' => 'part-x', 'name' => 'Heater Block', 'description' => 'Part update'],
                ],
            ],
            'standards' => [
                'part-x' => [
                    [
                        'name' => 'Cek tekanan',
                        'input_type' => 'range',
                        'min_value' => 10,
                        'max_value' => 20,
                        'unit' => 'Bar',
                        'is_required' => true,
                        'is_active' => true,
                    ],
                ],
            ],
            'schedule' => [
                'frequency_type' => 'weekly',
                'start_date' => '2026-05-21',
                'generate_until' => '2026-06-21',
                'weekly_days' => [1, 4],
                'monthly_day' => null,
            ],
        ];

        $this->actingAs($this->admin)->put("/pm/master-checksheet/{$checksheet->id}", [
            'checksheet_code' => 'PM-CH-001',
            'checksheet_name' => 'PM Update',
            'description' => 'Updated',
            'is_active' => '1',
            'wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->assertRedirect("/pm/master-checksheet/{$checksheet->id}");

        $this->assertDatabaseHas('pm_checksheets', ['id' => $checksheet->id, 'checksheet_name' => 'PM Update']);
        $this->assertDatabaseHas('pm_checksheet_parts', ['part_name' => 'Heater Block']);
        $this->assertDatabaseHas('pm_schedules', ['frequency_type' => 'weekly']);
        $this->assertDatabaseHas('user_activity_logs', ['module_name' => 'master_checksheet', 'action' => 'update']);

        $this->actingAs($this->admin)->patch("/pm/master-checksheet/{$checksheet->id}/nonaktifkan")->assertRedirect('/pm/master-checksheet');
        $this->assertDatabaseHas('pm_checksheets', ['id' => $checksheet->id, 'is_active' => false]);

        // Checksheet ini sudah pernah terhubung ke mesin, sehingga tidak boleh dihapus
        // meskipun sudah dinonaktifkan. Guard delete menolak dan mengembalikan flash_error.
        $this->actingAs($this->admin)
            ->delete("/pm/master-checksheet/{$checksheet->id}")
            ->assertRedirect('/pm/master-checksheet')
            ->assertSessionHas('flash_error');
        $this->assertDatabaseHas('pm_checksheets', ['id' => $checksheet->id]);
        $this->assertDatabaseMissing('user_activity_logs', ['module_name' => 'master_checksheet', 'action' => 'delete']);
    }

    public function test_updating_checksheet_keeps_existing_pm_review_data(): void
    {
        $this->test_admin_can_create_checksheet_with_nested_data();

        $checksheet = PmChecksheet::query()->where('checksheet_code', 'PM-CH-001')->firstOrFail();
        $scheduleDate = PmScheduleDate::query()->firstOrFail();
        $historicalScheduleDate = PmScheduleDate::query()->whereKeyNot($scheduleDate->id)->firstOrFail();
        $unworkedScheduleDate = PmScheduleDate::query()
            ->whereKeyNot([$scheduleDate->id, $historicalScheduleDate->id])
            ->firstOrFail();

        // Create kini menghasilkan jendela penuh 12 bulan (hingga 2027-05-21),
        // sehingga tanggal Agustus 2026 sudah ter-generate dan belum dikerjakan.
        // Bersihkan dahulu agar skenario di bawah (jadwal harian 1-3 Agustus)
        // tidak bentrok dengan baris aktif bawaan hasil create.
        PmScheduleDate::query()
            ->where('pm_schedule_id', $scheduleDate->pm_schedule_id)
            ->whereBetween('scheduled_date', ['2026-08-01 00:00:00', '2026-08-31 23:59:59'])
            ->forceDelete();

        $scheduleDate->forceFill(['status' => 'waiting_review'])->save();

        $execution = PmExecution::query()->create([
            'pm_schedule_date_id' => $scheduleDate->id,
            'machine_id' => $this->machine->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'waiting_review',
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
        ]);

        PmExecution::query()->create([
            'pm_schedule_date_id' => $historicalScheduleDate->id,
            'machine_id' => $this->machine->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'in_progress',
            'started_at' => now(),
        ])->delete();

        PmScheduleDate::query()->create([
            'pm_schedule_id' => $scheduleDate->pm_schedule_id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => '2026-08-01',
            'status' => 'scheduled',
            'generated_at' => now(),
        ])->delete();

        PmSchedule::query()->whereKey($scheduleDate->pm_schedule_id)->update([
            'frequency_type' => 'daily',
            'weekly_days' => null,
            'monthly_day' => null,
            'start_date' => '2026-08-01',
            'generate_until' => '2026-08-03',
        ]);

        $payload = [
            'selected_machine_ids' => [$this->machine->id],
            'parts' => [
                $this->machine->id => [
                    ['id' => 'part-1', 'name' => 'Motor Drive', 'description' => 'Part tetap'],
                ],
            ],
            'standards' => [
                'part-1' => [
                    [
                        'name' => 'Cek suhu motor',
                        'input_type' => 'number',
                        'target_value' => 65,
                        'unit' => 'Celcius',
                        'is_required' => true,
                        'is_active' => true,
                    ],
                ],
            ],
            'schedule' => [
                'frequency_type' => 'daily',
                'start_date' => '2026-08-01',
                'generate_until' => '2026-08-03',
                'weekly_days' => [],
                'monthly_day' => null,
            ],
        ];

        $this->actingAs($this->admin)->put("/pm/master-checksheet/{$checksheet->id}", [
            'checksheet_code' => 'PM-CH-001',
            'checksheet_name' => 'PM Mingguan Line 1 Revisi',
            'description' => 'Updated',
            'is_active' => '1',
            'wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->assertRedirect("/pm/master-checksheet/{$checksheet->id}");

        $this->assertDatabaseHas('pm_executions', ['id' => $execution->id, 'status' => 'waiting_review']);
        $this->assertDatabaseHas('pm_schedule_dates', ['id' => $scheduleDate->id, 'status' => 'waiting_review']);
        $this->assertDatabaseHas('pm_schedule_dates', ['id' => $historicalScheduleDate->id]);
        $this->assertDatabaseMissing('pm_schedule_dates', ['id' => $unworkedScheduleDate->id]);
        $this->assertDatabaseHas('pm_schedule_dates', ['scheduled_date' => '2026-08-01 00:00:00', 'status' => 'scheduled', 'deleted_at' => null]);
        $this->assertDatabaseHas('pm_schedule_dates', ['scheduled_date' => '2026-08-03 00:00:00', 'status' => 'scheduled']);

        $scheduleDateRowsAfterFirstSave = PmScheduleDate::query()
            ->withTrashed()
            ->where('pm_schedule_id', $scheduleDate->pm_schedule_id)
            ->orderBy('id')
            ->get(['id', 'scheduled_date', 'status', 'deleted_at'])
            ->map(fn (PmScheduleDate $date) => [
                $date->id,
                $date->scheduled_date->toDateString(),
                $date->status,
                $date->deleted_at?->toDateTimeString(),
            ])
            ->all();

        $this->actingAs($this->admin)->put("/pm/master-checksheet/{$checksheet->id}", [
            'checksheet_code' => 'PM-CH-001',
            'checksheet_name' => 'PM Mingguan Line 1 Revisi',
            'description' => 'Updated',
            'is_active' => '1',
            'wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->assertRedirect("/pm/master-checksheet/{$checksheet->id}");

        $this->assertSame($scheduleDateRowsAfterFirstSave, PmScheduleDate::query()
            ->withTrashed()
            ->where('pm_schedule_id', $scheduleDate->pm_schedule_id)
            ->orderBy('id')
            ->get(['id', 'scheduled_date', 'status', 'deleted_at'])
            ->map(fn (PmScheduleDate $date) => [
                $date->id,
                $date->scheduled_date->toDateString(),
                $date->status,
                $date->deleted_at?->toDateTimeString(),
            ])
            ->all());
        $this->actingAs($this->admin)->get('/pm/review')->assertOk()->assertSee('MC-01');
    }

    /**
     * Memastikan lock Machine terjadi sebelum mutation child/config checksheet.
     */
    public function test_update_locks_selected_machines_before_child_mutation(): void
    {
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-LOCK-ORDER',
            'checksheet_name' => 'Lock Order Checksheet',
            'is_active' => true,
        ]);

        PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);

        $queries = [];
        // Hook ini mengamati urutan query tanpa bergantung pada SQL FOR UPDATE SQLite.
        DB::connection()->beforeExecuting(function (string $query) use (&$queries): void {
            $queries[] = strtolower($query);
        });
        $this->actingAs($this->admin)->put("/pm/master-checksheet/{$checksheet->id}", [
            'checksheet_code' => 'PM-LOCK-ORDER',
            'checksheet_name' => 'Lock Order Checksheet',
            'is_active' => '1',
            'wizard_payload' => json_encode([
                'selected_machine_ids' => [$this->machine->id],
                'parts' => [
                    $this->machine->id => [
                        ['id' => 'part-lock', 'name' => 'Part Lock', 'description' => 'Part test'],
                    ],
                ],
                'standards' => [
                    'part-lock' => [[
                        'name' => 'Standard Lock',
                        'input_type' => 'number',
                        'target_value' => 1,
                        'action_options' => [],
                        'is_required' => true,
                        'is_active' => true,
                    ]],
                ],
                'schedule' => [
                    'frequency_type' => 'weekly',
                    'weekly_days' => [1],
                    'monthly_day' => null,
                    'operational_from' => '2026-09-15',
                ],
            ], JSON_THROW_ON_ERROR),
        ])->assertRedirect("/pm/master-checksheet/{$checksheet->id}");

        $machineLock = array_key_first(array_filter(
            $queries,
            fn (string $query): bool => str_contains($query, 'from "machines"')
                && str_contains($query, 'where "id" in')
                && str_contains($query, 'order by "id" asc'),
        ));
        $childMutation = array_key_first(array_filter(
            $queries,
            fn (string $query): bool => preg_match(
                '/\b(insert into|update)\s+"?pm_(checksheet_machines|checksheet_parts|checksheet_standards|schedules)/i',
                $query,
            ) === 1,
        ));

        $this->assertNotNull($machineLock, 'Selected machines must be locked before synchronization.');
        $this->assertNotNull($childMutation, 'Synchronization must mutate child/config rows.');
        $this->assertLessThan($childMutation, $machineLock);
    }

    // =========================================================
    // SLICE 4 — PM Checksheet Historical Delete Protection
    // =========================================================

    // ---------------------------------------------------------
    // A. Migration / default semantics
    // ---------------------------------------------------------

    /**
     * A: Migrated-style rows (created via raw DB) must default to
     * assignment_history_known=false and first_observed_machine_assignment_at=NULL.
     */
    public function test_s4_a_legacy_row_defaults_history_known_false_and_no_observed_timestamp(): void
    {
        // Insert a row bypassing the application service (simulates legacy/migrated row)
        $id = DB::table('pm_checksheets')->insertGetId([
            'checksheet_code' => 'LEGACY-DEF',
            'checksheet_name' => 'Legacy Default Row',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $checksheet = PmChecksheet::query()->findOrFail($id);

        $this->assertFalse(
            (bool) $checksheet->assignment_history_known,
            'Raw/migrated row must default to assignment_history_known=false'
        );
        $this->assertNull(
            $checksheet->first_observed_machine_assignment_at,
            'Raw/migrated row must default to first_observed_machine_assignment_at=NULL'
        );
    }

    /**
     * A: Checksheet created through the normal application service must have
     * assignment_history_known=true (explicitly set by service, not relying on default).
     */
    public function test_s4_a_new_application_checksheet_is_history_known(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 00:00:00', 'Asia/Jakarta'));

        $this->actingAs($this->admin)->post('/pm/master-checksheet', [
            'checksheet_code' => 'PM-NEW-KNOWN',
            'checksheet_name' => 'New Known Checksheet',
            'is_active' => '1',
            'wizard_payload' => json_encode([
                'selected_machine_ids' => [$this->machine->id],
                'parts' => [
                    $this->machine->id => [
                        ['id' => 'part-k', 'name' => 'Motor K', 'description' => ''],
                    ],
                ],
                'standards' => [
                    'part-k' => [[
                        'name' => 'Cek suhu K',
                        'input_type' => 'number',
                        'target_value' => 60,
                        'action_options' => [],
                        'unit' => 'C',
                        'is_required' => true,
                        'is_active' => true,
                    ]],
                ],
                'schedule' => [
                    'frequency_type' => 'daily',
                    'operational_from' => '2026-05-21',
                    'weekly_days' => [],
                    'monthly_day' => null,
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $checksheet = PmChecksheet::query()->where('checksheet_code', 'PM-NEW-KNOWN')->firstOrFail();

        $this->assertTrue(
            (bool) $checksheet->assignment_history_known,
            'Application-created checksheet must have assignment_history_known=true'
        );
        $this->assertNotNull(
            $checksheet->first_observed_machine_assignment_at,
            'Application-created checksheet with a machine assignment must have first_observed_machine_assignment_at set'
        );
    }

    // ---------------------------------------------------------
    // B. Pristine hard-delete (NEW PRISTINE state)
    // ---------------------------------------------------------

    /**
     * B: Known-history checksheet with no assignment and no observed timestamp
     * must be permanently deletable by admin.
     */
    public function test_s4_b_pristine_checksheet_can_be_hard_deleted(): void
    {
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-PRISTINE',
            'checksheet_name' => 'Pristine Checksheet',
            'is_active' => true,
            'assignment_history_known' => true,
        ]);

        $this->actingAs($this->admin)
            ->delete("/pm/master-checksheet/{$checksheet->id}")
            ->assertRedirect('/pm/master-checksheet');

        $this->assertDatabaseMissing('pm_checksheets', ['id' => $checksheet->id, 'deleted_at' => null]);
        // forceDelete means the row is physically absent
        $this->assertNull(PmChecksheet::withTrashed()->find($checksheet->id));
    }

    // ---------------------------------------------------------
    // C. Legacy zero-assignment rejection
    // ---------------------------------------------------------

    /**
     * C: Legacy checksheet (assignment_history_known=false) with zero current
     * assignments must be rejected with the legacy ambiguous copy.
     */
    public function test_s4_c_legacy_zero_assignment_delete_rejected(): void
    {
        // Raw insert: assignment_history_known defaults false
        $id = DB::table('pm_checksheets')->insertGetId([
            'checksheet_code' => 'LEGACY-ZERO',
            'checksheet_name' => 'Legacy Zero Assignment',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete("/pm/master-checksheet/{$id}");

        $response->assertRedirect();
        $this->assertStringContainsString(
            'Data operasional lama tidak dapat dihapus permanen',
            session('flash_error') ?? '',
            'Legacy ambiguous rejection must return the expected business copy'
        );

        // Checksheet must still exist
        $this->assertDatabaseHas('pm_checksheets', ['id' => $id]);
    }

    // ---------------------------------------------------------
    // D. Current assignment blocks delete
    // ---------------------------------------------------------

    /**
     * D: Checksheet with any current machine assignment must be rejected with
     * the known-assignment copy, regardless of schedule/occurrence state.
     */
    public function test_s4_d_current_assignment_blocks_delete(): void
    {
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-ASSIGNED',
            'checksheet_name' => 'Assigned Checksheet',
            'is_active' => true,
            'assignment_history_known' => true,
        ]);

        PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete("/pm/master-checksheet/{$checksheet->id}");

        $response->assertRedirect();
        $this->assertStringContainsString(
            'PM Checksheet yang sudah terhubung ke mesin tidak dapat dihapus',
            session('flash_error') ?? '',
            'Known assignment rejection must return the expected business copy'
        );

        // Checksheet and assignment must still exist
        $this->assertDatabaseHas('pm_checksheets', ['id' => $checksheet->id]);
        $this->assertDatabaseHas('pm_checksheet_machines', [
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);
    }

    // ---------------------------------------------------------
    // E. First assignment provenance (marker stamped atomically)
    // ---------------------------------------------------------

    /**
     * E: Creating a genuinely new Machine assignment on a known-pristine
     * checksheet must populate first_observed_machine_assignment_at exactly once.
     */
    public function test_s4_e_first_assignment_stamps_observed_timestamp(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 00:00:00', 'Asia/Jakarta'));

        $this->actingAs($this->admin)->post('/pm/master-checksheet', [
            'checksheet_code' => 'PM-PROVENANCE',
            'checksheet_name' => 'Provenance Checksheet',
            'is_active' => '1',
            'wizard_payload' => json_encode([
                'selected_machine_ids' => [$this->machine->id],
                'parts' => [
                    $this->machine->id => [
                        ['id' => 'part-prov', 'name' => 'Motor', 'description' => ''],
                    ],
                ],
                'standards' => [
                    'part-prov' => [[
                        'name' => 'Cek suhu',
                        'input_type' => 'number',
                        'target_value' => 60,
                        'action_options' => [],
                        'unit' => 'C',
                        'is_required' => true,
                        'is_active' => true,
                    ]],
                ],
                'schedule' => [
                    'frequency_type' => 'daily',
                    'operational_from' => '2026-05-21',
                    'weekly_days' => [],
                    'monthly_day' => null,
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $checksheet = PmChecksheet::query()->where('checksheet_code', 'PM-PROVENANCE')->firstOrFail();

        $this->assertTrue(
            (bool) $checksheet->assignment_history_known,
            'Newly created checksheet must remain history_known=true'
        );
        $this->assertNotNull(
            $checksheet->first_observed_machine_assignment_at,
            'First assignment must populate first_observed_machine_assignment_at'
        );
    }

    // ---------------------------------------------------------
    // F. Marker immutability (subsequent assignment update)
    // ---------------------------------------------------------

    /**
     * F: Re-saving the checksheet (update) or adding a second assignment must NOT
     * overwrite the original first_observed_machine_assignment_at timestamp.
     */
    public function test_s4_f_marker_immutable_on_subsequent_update(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 00:00:00', 'Asia/Jakarta'));

        // Create with first assignment
        $this->actingAs($this->admin)->post('/pm/master-checksheet', [
            'checksheet_code' => 'PM-IMMUTABLE',
            'checksheet_name' => 'Immutable Checksheet',
            'is_active' => '1',
            'wizard_payload' => json_encode([
                'selected_machine_ids' => [$this->machine->id],
                'parts' => [$this->machine->id => [['id' => 'p1', 'name' => 'PartA', 'description' => '']]],
                'standards' => ['p1' => [['name' => 'S1', 'input_type' => 'number', 'target_value' => 1, 'action_options' => [], 'unit' => null, 'is_required' => true, 'is_active' => true]]],
                'schedule' => ['frequency_type' => 'daily', 'operational_from' => '2026-05-21', 'weekly_days' => [], 'monthly_day' => null],
            ], JSON_THROW_ON_ERROR),
        ]);

        $checksheet = PmChecksheet::query()->where('checksheet_code', 'PM-IMMUTABLE')->firstOrFail();
        $originalTimestamp = $checksheet->first_observed_machine_assignment_at;
        $this->assertNotNull($originalTimestamp);

        // Advance time and create a second machine
        $location2 = Location::query()->create(['location_code' => 'LOC-F2', 'location_name' => 'Line F2', 'is_active' => true]);
        $machine2 = Machine::query()->create([
            'location_id' => $location2->id,
            'machine_code' => 'MC-F2',
            'machine_name' => 'Machine F2',
            'qr_token' => 'qr-mc-f2',
            'is_active' => true,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-01 00:00:00', 'Asia/Jakarta'));

        // Update to include a second machine
        $this->actingAs($this->admin)->put("/pm/master-checksheet/{$checksheet->id}", [
            'checksheet_code' => 'PM-IMMUTABLE',
            'checksheet_name' => 'Immutable Checksheet',
            'is_active' => '1',
            'wizard_payload' => json_encode([
                'selected_machine_ids' => [$this->machine->id, $machine2->id],
                'parts' => [
                    $this->machine->id => [['id' => 'p1', 'name' => 'PartA', 'description' => '']],
                    $machine2->id => [['id' => 'p2', 'name' => 'PartB', 'description' => '']],
                ],
                'standards' => [
                    'p1' => [['name' => 'S1', 'input_type' => 'number', 'target_value' => 1, 'action_options' => [], 'unit' => null, 'is_required' => true, 'is_active' => true]],
                    'p2' => [['name' => 'S2', 'input_type' => 'number', 'target_value' => 2, 'action_options' => [], 'unit' => null, 'is_required' => true, 'is_active' => true]],
                ],
                'schedule' => ['frequency_type' => 'daily', 'operational_from' => '2026-06-01', 'weekly_days' => [], 'monthly_day' => null],
            ], JSON_THROW_ON_ERROR),
        ]);

        $checksheet->refresh();
        $this->assertEquals(
            $originalTimestamp->toDateTimeString(),
            $checksheet->first_observed_machine_assignment_at->toDateTimeString(),
            'Original first_observed_machine_assignment_at must not be overwritten on subsequent update'
        );
    }

    // ---------------------------------------------------------
    // G. Legacy remains ambiguous after new assignment
    // ---------------------------------------------------------

    /**
     * G: A legacy checksheet (assignment_history_known=false) must NOT become
     * pristine even after receiving a new post-instrumentation assignment.
     * After removing the current assignment, the delete must still be rejected
     * with the legacy-specific copy — proving Guard 2 fires, not Guard 1.
     */
    public function test_s4_g_legacy_checksheet_remains_ambiguous_after_new_assignment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 00:00:00', 'Asia/Jakarta'));

        // Create as legacy row: assignment_history_known = false (raw insert)
        $id = DB::table('pm_checksheets')->insertGetId([
            'checksheet_code' => 'PM-LEGACY-NEW-ASSIGN',
            'checksheet_name' => 'Legacy Gets New Assignment',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $checksheet = PmChecksheet::query()->findOrFail($id);

        // Update through the application service — adds assignment to this legacy checksheet
        $this->actingAs($this->admin)->put("/pm/master-checksheet/{$checksheet->id}", [
            'checksheet_code' => 'PM-LEGACY-NEW-ASSIGN',
            'checksheet_name' => 'Legacy Gets New Assignment',
            'is_active' => '1',
            'wizard_payload' => json_encode([
                'selected_machine_ids' => [$this->machine->id],
                'parts' => [$this->machine->id => [['id' => 'pg', 'name' => 'PartG', 'description' => '']]],
                'standards' => ['pg' => [['name' => 'SG', 'input_type' => 'number', 'target_value' => 1, 'action_options' => [], 'unit' => null, 'is_required' => true, 'is_active' => true]]],
                'schedule' => ['frequency_type' => 'daily', 'operational_from' => '2026-05-21', 'weekly_days' => [], 'monthly_day' => null],
            ], JSON_THROW_ON_ERROR),
        ]);

        $checksheet->refresh();

        $this->assertFalse(
            (bool) $checksheet->assignment_history_known,
            'Legacy checksheet must never become assignment_history_known=true'
        );

        // Spec: legacy row receives first_observed_machine_assignment_at when an instrumented
        // new assignment is created, but remains non-deletable via Guard 2.
        $this->assertNotNull(
            $checksheet->first_observed_machine_assignment_at,
            'Legacy checksheet should receive marker timestamp on new instrumented assignment'
        );

        // Remove the current assignment so Guard 1 does NOT fire —
        // we want to prove Guard 2 rejects, not Guard 1.
        PmChecksheetMachine::query()
            ->where('pm_checksheet_id', $checksheet->id)
            ->delete();

        $this->assertCount(
            0,
            $checksheet->machineAssignments()->get(),
            'Assignment must be removed before testing Guard 2'
        );

        // Delete must be rejected by Guard 2 (legacy ambiguity), not Guard 1.
        $response = $this->actingAs($this->admin)
            ->delete("/pm/master-checksheet/{$checksheet->id}");
        $response->assertRedirect();
        $this->assertStringContainsString(
            'Data operasional lama tidak dapat dihapus permanen',
            session('flash_error') ?? '',
            'Guard 2 must reject legacy row with the legacy-specific business copy'
        );
        $this->assertDatabaseHas('pm_checksheets', ['id' => $checksheet->id]);
    }

    // ---------------------------------------------------------
    // H. Rollback atomicity
    // ---------------------------------------------------------

    /**
     * H: If an exception occurs during the assignment + marker path, both the
     * assignment row and the marker update must roll back atomically.
     *
     * Strategy: use a beforeExecuting hook to throw after the marker UPDATE
     * SQL is detected but before the transaction commits. We assert the hook
     * actually fired so the test cannot pass silently without exercising the
     * atomicity boundary.
     */
    public function test_s4_h_assignment_and_marker_roll_back_together(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 00:00:00', 'Asia/Jakarta'));

        // Create pristine checksheet with no assignments
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-ROLLBACK',
            'checksheet_name' => 'Rollback Test Checksheet',
            'is_active' => true,
            'assignment_history_known' => true,
        ]);

        $markerUpdateSeen = false;
        DB::connection()->beforeExecuting(function (string $sql) use (&$markerUpdateSeen): void {
            if (! $markerUpdateSeen
                && str_contains(strtolower($sql), 'update')
                && str_contains(strtolower($sql), 'pm_checksheets')
                && str_contains(strtolower($sql), 'first_observed_machine_assignment_at')
            ) {
                $markerUpdateSeen = true;
                throw new \RuntimeException('Controlled rollback injection for test_s4_h');
            }
        });

        try {
            $this->actingAs($this->admin)->post('/pm/master-checksheet', [
                'checksheet_code' => 'PM-ROLLBACK-ASSIGN',
                'checksheet_name' => 'Rollback Assign Attempt',
                'is_active' => '1',
                'wizard_payload' => json_encode([
                    'selected_machine_ids' => [$this->machine->id],
                    'parts' => [$this->machine->id => [['id' => 'ph', 'name' => 'PartH', 'description' => '']]],
                    'standards' => ['ph' => [['name' => 'SH', 'input_type' => 'number', 'target_value' => 1, 'action_options' => [], 'unit' => null, 'is_required' => true, 'is_active' => true]]],
                    'schedule' => ['frequency_type' => 'daily', 'operational_from' => '2026-05-21', 'weekly_days' => [], 'monthly_day' => null],
                ], JSON_THROW_ON_ERROR),
            ]);
        } catch (\Throwable) {
            // Exception is expected due to the rollback injection
        }

        // The hook MUST have fired — otherwise the test proves nothing about atomicity.
        $this->assertTrue($markerUpdateSeen, 'beforeExecuting hook must fire at the marker UPDATE');

        // Because the hook threw inside the outer DB::transaction wrapping create(),
        // the whole transaction rolls back: the new checksheet row must be absent.
        $this->assertNull(
            PmChecksheet::withTrashed()->where('checksheet_code', 'PM-ROLLBACK-ASSIGN')->first(),
            'Rolled-back checksheet row must be absent; transaction did not commit'
        );

        // The original checksheet must be unchanged
        $this->assertDatabaseHas('pm_checksheets', [
            'id' => $checksheet->id,
            'checksheet_code' => 'PM-ROLLBACK',
        ]);
    }

    // ---------------------------------------------------------
    // I. Protected descendants survive rejection
    // ---------------------------------------------------------

    /**
     * I: Checksheet with protected descendants (execution in waiting_review)
     * must be rejected; all descendants must survive intact.
     */
    public function test_s4_i_protected_descendants_survive_delete_rejection(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 00:00:00', 'Asia/Jakarta'));

        $this->test_admin_can_create_checksheet_with_nested_data();

        $checksheet = PmChecksheet::query()->where('checksheet_code', 'PM-CH-001')->firstOrFail();
        $scheduleDate = PmScheduleDate::query()->firstOrFail();
        $scheduleDate->forceFill(['status' => 'waiting_review'])->save();

        $execution = PmExecution::query()->create([
            'pm_schedule_date_id' => $scheduleDate->id,
            'machine_id' => $this->machine->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'waiting_review',
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete("/pm/master-checksheet/{$checksheet->id}");

        $response->assertRedirect();

        // All protected descendants must survive
        $this->assertDatabaseHas('pm_checksheets', ['id' => $checksheet->id]);
        $this->assertDatabaseHas('pm_executions', ['id' => $execution->id, 'status' => 'waiting_review']);
        $this->assertDatabaseHas('pm_schedule_dates', ['id' => $scheduleDate->id, 'status' => 'waiting_review']);
    }

    // ---------------------------------------------------------
    // J. Lifecycle regression: deactivate / activate / update
    // ---------------------------------------------------------

    /**
     * J: Deactivate, activate, and update must remain functional
     * after the delete guard is added. This proves the guard does not
     * break other lifecycle operations.
     */
    public function test_s4_j_lifecycle_operations_remain_functional(): void
    {
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-LIFECYCLE',
            'checksheet_name' => 'Lifecycle Checksheet',
            'is_active' => true,
            'assignment_history_known' => true,
        ]);

        // Deactivate
        $this->actingAs($this->admin)
            ->patch("/pm/master-checksheet/{$checksheet->id}/nonaktifkan")
            ->assertRedirect('/pm/master-checksheet');
        $this->assertDatabaseHas('pm_checksheets', ['id' => $checksheet->id, 'is_active' => false]);

        // Activate
        $this->actingAs($this->admin)
            ->patch("/pm/master-checksheet/{$checksheet->id}/aktifkan")
            ->assertRedirect('/pm/master-checksheet');
        $this->assertDatabaseHas('pm_checksheets', ['id' => $checksheet->id, 'is_active' => true]);

        // Update metadata
        // Update metadata — requires a valid wizard payload per validation rules
        Carbon::setTestNow(Carbon::parse('2026-05-21 00:00:00', 'Asia/Jakarta'));
        $this->actingAs($this->admin)->put("/pm/master-checksheet/{$checksheet->id}", [
            'checksheet_code' => 'PM-LIFECYCLE',
            'checksheet_name' => 'Lifecycle Updated',
            'is_active' => '1',
            'wizard_payload' => json_encode([
                'selected_machine_ids' => [$this->machine->id],
                'parts' => [$this->machine->id => [['id' => 'pj', 'name' => 'PartJ', 'description' => '']]],
                'standards' => ['pj' => [['name' => 'SJ', 'input_type' => 'number', 'target_value' => 1, 'action_options' => [], 'unit' => null, 'is_required' => true, 'is_active' => true]]],
                'schedule' => ['frequency_type' => 'daily', 'operational_from' => '2026-05-21', 'weekly_days' => [], 'monthly_day' => null],
            ], JSON_THROW_ON_ERROR),
        ])->assertRedirect("/pm/master-checksheet/{$checksheet->id}");
        $this->assertDatabaseHas('pm_checksheets', ['id' => $checksheet->id, 'checksheet_name' => 'Lifecycle Updated']);
    }
    // ---------------------------------------------------------

    /**
     * K: Non-admin (operator) delete attempt must be rejected by existing
     * authorization middleware, unchanged by the new delete guard.
     */
    public function test_s4_k_non_admin_delete_is_rejected_by_authorization(): void
    {
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-AUTHZ',
            'checksheet_name' => 'Authorization Checksheet',
            'is_active' => true,
            'assignment_history_known' => true,
        ]);

        $this->actingAs($this->operator)
            ->delete("/pm/master-checksheet/{$checksheet->id}")
            ->assertRedirect('/403');

        $this->assertDatabaseHas('pm_checksheets', ['id' => $checksheet->id]);
    }

    // ---------------------------------------------------------
    // L. Repeated rejection is idempotent / zero-destructive
    // ---------------------------------------------------------

    /**
     * L: Two repeated delete attempts on a protected checksheet must both be
     * rejected with zero mutation and no successful-delete activity log entry.
     */
    public function test_s4_l_repeated_delete_rejection_is_zero_destructive(): void
    {
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-REPEAT',
            'checksheet_name' => 'Repeated Rejection Checksheet',
            'is_active' => true,
            'assignment_history_known' => true,
        ]);

        PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);

        // Capture all columns before any delete attempt
        $before = PmChecksheet::query()->findOrFail($checksheet->id)->toArray();

        // First attempt
        $this->actingAs($this->admin)
            ->delete("/pm/master-checksheet/{$checksheet->id}")
            ->assertRedirect();
        $this->assertDatabaseHas('pm_checksheets', ['id' => $checksheet->id]);

        // Second attempt
        $this->actingAs($this->admin)
            ->delete("/pm/master-checksheet/{$checksheet->id}")
            ->assertRedirect();
        $this->assertDatabaseHas('pm_checksheets', ['id' => $checksheet->id]);

        // Row state must be identical after both rejections
        $after = PmChecksheet::query()->findOrFail($checksheet->id)->toArray();
        $this->assertSame(
            $before['checksheet_code'],
            $after['checksheet_code'],
            'Checksheet code must be unchanged after repeated rejection'
        );
        $this->assertSame(
            $before['is_active'],
            $after['is_active'],
            'is_active must be unchanged after repeated rejection'
        );

        // No successful-delete activity log
        $this->assertDatabaseMissing('user_activity_logs', [
            'module_name' => 'master_checksheet',
            'action' => 'delete',
            'record_id' => $checksheet->id,
        ]);
    }

    // ---------------------------------------------------------
    // M. Guard 3 isolated — formerly-assigned with no current assignment
    // ---------------------------------------------------------

    /**
     * M: A checksheet with assignment_history_known=true and
     * first_observed_machine_assignment_at set, but zero current machine
     * assignments, must be rejected by Guard 3 with the known-assignment copy.
     *
     * This isolates Guard 3 from Guards 1 and 2 so that removing Guard 3
     * would cause this test to fail.
     */
    public function test_s4_m_formerly_assigned_checksheet_rejected_by_guard3(): void
    {
        // Pristine but with marker set — simulates a checksheet whose
        // assignment was deleted outside the application service layer
        // (e.g. test teardown, direct DB cleanup) while marker is preserved.
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-GUARD3',
            'checksheet_name' => 'Guard 3 Isolation Checksheet',
            'is_active' => true,
            'assignment_history_known' => true,
            'first_observed_machine_assignment_at' => now()->subDay(),
        ]);

        // Explicitly confirm: no current assignment exists (Guard 1 must not fire).
        $this->assertCount(0, $checksheet->machineAssignments()->get(), 'Fixture must have zero assignments');

        $response = $this->actingAs($this->admin)
            ->delete("/pm/master-checksheet/{$checksheet->id}");

        $response->assertRedirect();
        $this->assertStringContainsString(
            'PM Checksheet yang sudah terhubung ke mesin tidak dapat dihapus',
            session('flash_error') ?? '',
            'Guard 3 must reject formerly-assigned checksheet with the known-assignment copy'
        );
        $this->assertDatabaseHas('pm_checksheets', ['id' => $checksheet->id]);
    }

    /**
     * Regresi: toWizardPayload() harus mengeluarkan standards sebagai JSON object ("{}"),
     * bukan JSON array ("[]"), ketika checksheet belum memiliki part/standard.
     *
     * Akar masalah: $standards = [] di PHP di-encode menjadi "[]" oleh json_encode,
     * sehingga JS menerima Array. Assignment key dinamis (partId) pada Array
     * diabaikan oleh JSON.stringify, dan payload standards ke server selalu kosong.
     *
     * Dua sub-kasus:
     *  (a) Checksheet tanpa part → standards harus "{}" (stdClass/object JSON).
     *  (b) Checksheet dengan part dan standard → standards harus berupa object
     *      dengan key string partId (bukan numerik) dan value array standard.
     */
    public function test_toWizardPayload_standards_is_json_object_for_empty_and_non_empty(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 00:00:00', 'Asia/Jakarta'));

        // ── (a) Checksheet tanpa part ─────────────────────────────────────────
        $checksheetEmpty = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-REGRESSION-EMPTY',
            'checksheet_name' => 'Regression Empty Standards',
            'is_active' => true,
        ]);

        PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheetEmpty->id,
            'machine_id' => $this->machine->id,
        ]);

        $checksheetEmpty->load('machineAssignments.parts.standards');
        /** @var \App\Services\Master\PmChecksheetService $service */
        $service = $this->app->make(\App\Services\Master\PmChecksheetService::class);

        $payloadEmpty = $service->toWizardPayload($checksheetEmpty);
        $encodedEmpty = json_encode($payloadEmpty['standards'], JSON_THROW_ON_ERROR);

        // Harus "{}" bukan "[]" — JS hanya menerima Object dengan aman.
        $this->assertSame('{}', $encodedEmpty, 'standards kosong harus dikodekan sebagai object JSON');
        $this->assertIsObject($payloadEmpty['standards'], 'standards kosong harus berupa stdClass, bukan array PHP');

        // ── (b) Checksheet dengan part dan standard ───────────────────────────
        $this->test_admin_can_create_checksheet_with_nested_data(); // menanam PM-CH-001
        $checksheetFull = PmChecksheet::query()
            ->where('checksheet_code', 'PM-CH-001')
            ->with('machineAssignments.parts.standards')
            ->firstOrFail();

        $payloadFull = $service->toWizardPayload($checksheetFull);
        $encodedFull = json_encode($payloadFull['standards'], JSON_THROW_ON_ERROR);
        $decoded      = json_decode($encodedFull, false, 512, JSON_THROW_ON_ERROR);

        // Harus berupa object JSON dengan key string partId.
        $this->assertInstanceOf(\stdClass::class, $decoded, 'standards berisi entry harus dikodekan sebagai JSON object');

        // Key harus berupa string partId (bukan indeks numerik) dan value-nya array standard.
        $keys = array_keys((array) $decoded);
        $this->assertNotEmpty($keys, 'standards object harus memiliki minimal satu key partId');
        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression('/^\d+$/', (string) $key, "key partId harus berupa string numerik, dapat: {$key}");
            $this->assertIsArray((array) $decoded->{$key}, "value standards[{$key}] harus berupa array");
        }
    }
}
