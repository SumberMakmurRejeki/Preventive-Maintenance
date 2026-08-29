<?php

namespace Tests\Feature\Feature\Master;

use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MasterPmChecksheetOperationalWindowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::query()->create([
            'name' => 'Admin PRIME',
            'username' => 'admin.operational',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $location = Location::query()->create([
            'location_code' => 'LOC-OP',
            'location_name' => 'Operational Line',
            'is_active' => true,
        ]);

        $this->machine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'MC-OP',
            'machine_name' => 'Operational Machine',
            'qr_token' => 'qr-mc-op',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function basePayload(array $schedule): array
    {
        return [
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
            'schedule' => $schedule,
        ];
    }

    private function postCreate(array $payload): TestResponse
    {
        return $this->actingAs($this->admin)->post('/pm/master-checksheet', [
            'checksheet_code' => 'PM-OP-001',
            'checksheet_name' => 'Operational Checksheet',
            'description' => 'Test',
            'is_active' => '1',
            'wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
    }

    public function test_create_computes_start_and_generate_until_from_operational_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 00:00:00', 'Asia/Jakarta'));

        $payload = $this->basePayload([
            'frequency_type' => 'weekly',
            'operational_from' => '2026-06-01',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
        ]);

        $this->postCreate($payload)->assertRedirect();

        $schedule = PmSchedule::query()->firstOrFail();

        // planning_start = max(business_today, operational_from) = 2026-06-01
        // planning_end = 2026-06-01 + 12 bulan = 2027-06-01
        $this->assertSame('2026-06-01', $schedule->operational_from?->toDateString());
        $this->assertSame('2026-06-01', $schedule->start_date?->toDateString());
        $this->assertSame('2027-06-01', $schedule->generate_until?->toDateString());
    }

    public function test_update_accepts_unchanged_past_operational_from_and_clamps_planning_start(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 00:00:00', 'Asia/Jakarta'));

        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-CLAMP',
            'checksheet_name' => 'Clamp Checksheet',
            'is_active' => true,
        ]);
        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);
        PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'daily',
            'weekly_days' => null,
            'monthly_day' => null,
            'operational_from' => '2026-05-01',
            'start_date' => '2026-05-01',
            'generate_until' => '2026-06-01',
            'is_active' => true,
        ]);

        // Nilai operational_from di masa lalu TIDAK diubah -> diterima (kontrak
        // TASK-002 acceptance 12). planning_start di-clamp ke business_today
        // (batas bawah generation), sehingga generate_until = business_today + 12.
        $payload = $this->basePayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-05-01',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);

        $this->actingAs($this->admin)->put("/pm/master-checksheet/{$checksheet->id}", [
            'checksheet_code' => 'PM-CLAMP',
            'checksheet_name' => 'Clamp Updated',
            'description' => 'Updated',
            'is_active' => '1',
            'wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->assertRedirect();

        $schedule = PmSchedule::query()->firstOrFail();

        // start_date (batas operasional) tetap mengikuti operational_from.
        // planning_start yang di-clamp ke business_today hanya menjadi batas
        // bawah generation: generate_until = 2026-05-21 + 12 bulan = 2027-05-21.
        $this->assertSame('2026-05-01', $schedule->operational_from?->toDateString());
        $this->assertSame('2026-05-01', $schedule->start_date?->toDateString());
        $this->assertSame('2027-05-21', $schedule->generate_until?->toDateString());
    }

    public function test_update_rejects_operational_from_changed_to_past_from_null(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 00:00:00', 'Asia/Jakarta'));

        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-CLAMP-NULL',
            'checksheet_name' => 'Clamp Null Checksheet',
            'is_active' => true,
        ]);
        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);
        PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'daily',
            'weekly_days' => null,
            'monthly_day' => null,
            'operational_from' => null,
            'start_date' => '2026-01-01',
            'generate_until' => '2026-02-01',
            'is_active' => true,
        ]);

        // Jalur legacy NULL diubah ke tanggal masa lalu (sebelum business_today)
        // -> ditolak (kontrak TASK-002: update sebelum business date ditolak).
        $payload = $this->basePayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-05-01',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);

        $response = $this->actingAs($this->admin)->put("/pm/master-checksheet/{$checksheet->id}", [
            'checksheet_code' => 'PM-CLAMP-NULL',
            'checksheet_name' => 'Clamp Null Updated',
            'description' => 'Updated',
            'is_active' => '1',
            'wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        $response->assertSessionHasErrors('schedule.operational_from');

        // Tidak ada mutasi yang diterapkan.
        $schedule = PmSchedule::query()->firstOrFail();
        $this->assertNull($schedule->operational_from);
        $this->assertSame('2026-01-01', $schedule->start_date?->toDateString());
    }

    public function test_update_rejects_operational_from_changed_from_past_to_other_past(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 00:00:00', 'Asia/Jakarta'));

        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-CLAMP-PAST',
            'checksheet_name' => 'Clamp Past Checksheet',
            'is_active' => true,
        ]);
        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);
        PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'daily',
            'weekly_days' => null,
            'monthly_day' => null,
            'operational_from' => '2026-03-01',
            'start_date' => '2026-03-01',
            'generate_until' => '2026-04-01',
            'is_active' => true,
        ]);

        // Nilai lama (2026-03-01) dan nilai baru (2026-05-01) sama-sama di masa
        // lalu, namun nilainya DIUBAH -> ditolak (kontrak TASK-002 acceptance 14).
        $payload = $this->basePayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-05-01',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);

        $response = $this->actingAs($this->admin)->put("/pm/master-checksheet/{$checksheet->id}", [
            'checksheet_code' => 'PM-CLAMP-PAST',
            'checksheet_name' => 'Clamp Past Updated',
            'description' => 'Updated',
            'is_active' => '1',
            'wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        $response->assertSessionHasErrors('schedule.operational_from');

        $schedule = PmSchedule::query()->firstOrFail();
        $this->assertSame('2026-03-01', $schedule->operational_from?->toDateString());
    }

    public function test_update_accepts_operational_from_changed_to_on_or_after_business_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 00:00:00', 'Asia/Jakarta'));

        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-CLAMP-FUTURE',
            'checksheet_name' => 'Clamp Future Checksheet',
            'is_active' => true,
        ]);
        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);
        PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'daily',
            'weekly_days' => null,
            'monthly_day' => null,
            'operational_from' => '2026-05-01',
            'start_date' => '2026-05-01',
            'generate_until' => '2026-06-01',
            'is_active' => true,
        ]);

        // Nilai lama di masa lalu DIUBAH menjadi pada/ setelah business_today
        // (2026-05-21) -> diterima (kontrak TASK-002 acceptance 13).
        $payload = $this->basePayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-01',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);

        $this->actingAs($this->admin)->put("/pm/master-checksheet/{$checksheet->id}", [
            'checksheet_code' => 'PM-CLAMP-FUTURE',
            'checksheet_name' => 'Clamp Future Updated',
            'description' => 'Updated',
            'is_active' => '1',
            'wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->assertRedirect();

        $schedule = PmSchedule::query()->firstOrFail();
        $this->assertSame('2026-06-01', $schedule->operational_from?->toDateString());
        $this->assertSame('2026-06-01', $schedule->start_date?->toDateString());
        $this->assertSame('2027-06-01', $schedule->generate_until?->toDateString());
    }

    public function test_create_rejects_operational_from_before_business_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 00:00:00', 'Asia/Jakarta'));

        $payload = $this->basePayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-05-01',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);

        // Validasi memakai after_or_equal:business_date.today -> 2026-05-01 ditolak.
        $response = $this->postCreate($payload);
        $response->assertSessionHasErrors('schedule.operational_from');
        $this->assertDatabaseMissing('pm_schedules', ['frequency_type' => 'daily', 'start_date' => '2026-05-01']);
    }

    public function test_update_preserves_legacy_schedule_when_operational_from_absent(): void
    {
        // Buat checksheet melalui jalur langsung (langkah validasi create dilewati).
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-LEGACY',
            'checksheet_name' => 'Legacy Checksheet',
            'is_active' => true,
        ]);
        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);
        $schedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'weekly',
            'weekly_days' => [1],
            'operational_from' => null,
            'start_date' => '2026-01-05',
            'generate_until' => '2026-02-05',
            'is_active' => true,
        ]);

        // Update tanpa operational_from -> nilai legacy dipertahankan.
        $payload = $this->basePayload([
            'frequency_type' => 'weekly',
            'weekly_days' => [1],
            'monthly_day' => null,
        ]);

        $this->actingAs($this->admin)->put("/pm/master-checksheet/{$checksheet->id}", [
            'checksheet_code' => 'PM-LEGACY',
            'checksheet_name' => 'Legacy Updated',
            'description' => 'Updated',
            'is_active' => '1',
            'wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->assertRedirect();

        $schedule->refresh();
        $this->assertNull($schedule->operational_from);
        $this->assertSame('2026-01-05', $schedule->start_date?->toDateString());
        $this->assertSame('2026-02-05', $schedule->generate_until?->toDateString());
    }

    public function test_update_accepts_operational_from_nullable(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 00:00:00', 'Asia/Jakarta'));

        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-NULL',
            'checksheet_name' => 'Nullable Checksheet',
            'is_active' => true,
        ]);
        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);
        PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'daily',
            'weekly_days' => null,
            'monthly_day' => null,
            'operational_from' => '2026-05-21',
            'start_date' => '2026-05-21',
            'generate_until' => '2027-05-21',
            'is_active' => true,
        ]);

        // Update memakai operational_from yang null -> tidak gagal validasi bisnis.
        $payload = $this->basePayload([
            'frequency_type' => 'daily',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);

        $this->actingAs($this->admin)->put("/pm/master-checksheet/{$checksheet->id}", [
            'checksheet_code' => 'PM-NULL',
            'checksheet_name' => 'Nullable Updated',
            'description' => 'Updated',
            'is_active' => '1',
            'wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->assertRedirect();
    }

    public function test_apply_valid_payload_rejects_stored_null_schedule_without_mutation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 00:00:00', 'Asia/Jakarta'));
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-APPLY-NULL',
            'checksheet_name' => 'Apply Null Checksheet',
            'is_active' => true,
        ]);
        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);
        $schedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'daily',
            'weekly_days' => null,
            'monthly_day' => null,
            'operational_from' => null,
            'start_date' => '2026-01-01',
            'generate_until' => '2026-02-01',
            'is_active' => true,
        ]);
        PmScheduleDate::query()->create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => '2026-01-05',
            'status' => 'scheduled',
        ]);

        $payload = $this->basePayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-01',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/pm/master-checksheet/{$checksheet->id}/apply-schedule-update", [
                'checksheet_code' => 'PM-APPLY-NULL',
                'checksheet_name' => 'Apply Null Checksheet',
                'description' => 'Test',
                'is_active' => '1',
                'wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'token' => 'legacy-token',
                'confirmed' => true,
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'unresolved');

        $this->assertNull($schedule->fresh()->operational_from);
        $this->assertSame(1, PmScheduleDate::query()->count());
    }

    /**
     * Regression: manual reconciliation harus melaporkan unresolved schedule
     * ketika operational_from = NULL, bukan menampilkan "tidak ada perubahan".
     */
    public function test_manual_reconciliation_reports_unresolved_when_operational_from_null(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 00:00:00', 'Asia/Jakarta'));

        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-UNRESOLVED',
            'checksheet_name' => 'Unresolved Checksheet',
            'is_active' => true,
        ]);
        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);
        $schedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'daily',
            'weekly_days' => null,
            'monthly_day' => null,
            'operational_from' => null, // Legacy NULL
            'start_date' => '2026-01-01',
            'generate_until' => '2026-02-01',
            'is_active' => true,
        ]);

        $service = app(\App\Services\Master\PmChecksheetService::class);

        // Preview harus melaporkan unresolved
        $preview = $service->previewScheduleDates($checksheet);
        $this->assertTrue($preview['unresolved'] ?? false, 'Preview harus melaporkan unresolved ketika operational_from NULL');

        // Reconcile harus menolak dengan DomainException
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Terdapat jadwal dengan tanggal operasional yang belum ditetapkan');

        $service->reconcileScheduleDates(
            new \Illuminate\Http\Request(['confirmed' => true]),
            $checksheet
        );
    }
}
