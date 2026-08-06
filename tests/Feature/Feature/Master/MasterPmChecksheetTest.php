<?php

namespace Tests\Feature\Feature\Master;

use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmExecution;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                'start_date' => '2026-05-21',
                'generate_until' => '2026-05-25',
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

        $this->actingAs($this->admin)->delete("/pm/master-checksheet/{$checksheet->id}")->assertRedirect('/pm/master-checksheet');
        $this->assertDatabaseMissing('pm_checksheets', ['id' => $checksheet->id]);
        $this->assertDatabaseHas('user_activity_logs', ['module_name' => 'master_checksheet', 'action' => 'delete']);
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
}
