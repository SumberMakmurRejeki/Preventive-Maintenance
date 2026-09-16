<?php

namespace Tests\Feature\Feature\Master;

use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Models\User;
use App\Services\Master\PmChecksheetService;
use App\Services\Master\PmScheduleUpdateService;
use App\Services\PM\ScheduleLifecycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Guard tulis legacy untuk state terminal Schedule (TASK-006 Slice B).
 *
 * Otoritas transisi (ScheduleLifecycleService) memiliki state terminal
 * 'ended'. Writer legacy (wizard checksheet dan apply update jadwal) tidak
 * boleh menghidupkan kembali jadwal ENDED hanya karena jalur boolean
 * is_active menulis true lagi.
 */
class ScheduleLifecycleWriterGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Machine $machine;

    private PmChecksheet $checksheet;

    private PmSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-15 00:00:00', 'Asia/Jakarta'));

        // Otoritas lifecycle menulis audit melalui ActivityLogService yang
        // membaca session dari request aktif; bind request ber-session agar
        // pemanggilan langsung service pada test memakai jalur produksi.
        $request = Request::create('/pm/test', 'POST');
        $request->setLaravelSession(app('session.store'));
        $this->app->instance('request', $request);

        $this->admin = User::query()->create([
            'name' => 'Admin PRIME',
            'username' => 'admin.guard',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $location = Location::query()->create([
            'location_code' => 'LOC-GD',
            'location_name' => 'Guard Line',
            'is_active' => true,
        ]);

        $this->machine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'MC-GD',
            'machine_name' => 'Guard Machine',
            'qr_token' => 'qr-mc-gd',
            'is_active' => true,
        ]);

        $this->checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-GD-001',
            'checksheet_name' => 'Guard Checksheet',
            'is_active' => true,
        ]);

        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $this->checksheet->id,
            'machine_id' => $this->machine->id,
        ]);

        $this->schedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'weekly',
            'weekly_days' => [1, 4],
            'operational_from' => '2026-06-01',
            'start_date' => '2026-06-01',
            'generate_until' => '2026-09-01',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Mengakhiri jadwal melalui otoritas transisi sebelum uji guard writer.
     */
    private function endScheduleViaAuthority(): void
    {
        app(ScheduleLifecycleService::class)->transition($this->schedule, 'ended', 'Akhiri untuk pengujian');
    }

    /**
     * Payload wizard minimal dengan struktur sama seperti endpoint master.
     *
     * @return array<string, mixed>
     */
    private function wizardPayload(array $schedule): array
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
                        'is_required' => true,
                    ],
                ],
            ],
            'schedule' => $schedule,
        ];
    }

    /**
     * Payload schedule wizard standar.
     *
     * @return array<string, mixed>
     */
    private function schedulePayload(): array
    {
        return [
            'frequency_type' => 'weekly',
            'operational_from' => '2026-06-01',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
        ];
    }

    /**
     * Menegaskan baris jadwal tetap ENDED tanpa boundary baru.
     */
    private function assertScheduleStillEnded(): void
    {
        $schedule = $this->schedule->fresh();
        $this->assertSame('ended', $schedule->lifecycle_status);
        $this->assertFalse((bool) $schedule->is_active);
        $this->assertNull($schedule->effective_live_from);
    }

    /**
     * Apply update jadwal tidak boleh menghidupkan kembali jadwal ENDED.
     *
     * TASK-006 Slice C: assignment hanya-ENDED dilaporkan sebagai dormant pada
     * hasil apply (bukan kegagalan seluruh update), era ENDED tetap utuh, dan
     * era baru tidak dibuat secara implisit.
     */
    public function test_schedule_update_apply_cannot_revive_ended_schedule(): void
    {
        $this->endScheduleViaAuthority();
        $assignment = $this->schedule->pm_checksheet_machine_id;
        $payload = $this->wizardPayload($this->schedulePayload());

        // Preview baru setelah ENDED supaya token cocok dan apply mencapai writer.
        $preview = $this->actingAs($this->admin)->postJson(
            "/pm/master-checksheet/{$this->checksheet->id}/preview-schedule-update",
            ['wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR)],
        );
        $preview->assertOk();
        $this->assertSame('dormant_skipped', $preview->json('dormant_assignments.0.status'));

        $apply = $this->actingAs($this->admin)->postJson(
            "/pm/master-checksheet/{$this->checksheet->id}/apply-schedule-update",
            [
                'wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'token' => $preview->json('token'),
                'confirmed' => true,
            ],
        );
        $apply->assertOk();
        $apply->assertJsonPath('status', 'applied');
        $apply->assertJsonPath('dormant_assignments.0.status', 'dormant_skipped');
        $apply->assertJsonPath('dormant_assignments.0.assignment_id', $assignment);

        $this->assertScheduleStillEnded();
        $this->assertSame(
            1,
            PmSchedule::query()->where('pm_checksheet_machine_id', $assignment)->count(),
            'Era baru tidak boleh dibuat secara implisit untuk assignment dormant.',
        );
    }

    /**
     * Existing PAUSED schedule boleh dikonfigurasi tanpa implicit resume,
     * boundary tetap, dan materialisasi tidak dijalankan.
     */
    public function test_schedule_update_apply_preserves_paused_schedule(): void
    {
        $this->schedule->forceFill([
            'lifecycle_status' => 'paused',
            'is_active' => false,
            'effective_live_from' => '2026-06-10',
        ])->save();

        $payload = $this->schedulePayload();
        $scheduleService = app(PmScheduleUpdateService::class);
        $preview = $scheduleService->preview($this->checksheet, $payload);
        $scheduleService->apply($this->checksheet, $payload, $preview['token']);

        $schedule = $this->schedule->fresh();
        $this->assertSame('paused', $schedule->lifecycle_status);
        $this->assertFalse((bool) $schedule->is_active);
        $this->assertSame('2026-06-10', $schedule->effective_live_from?->toDateString());
        $this->assertSame(0, PmScheduleDate::query()->where('pm_schedule_id', $schedule->id)->count());
    }

    /**
     * Wizard checksheet juga tidak boleh menghidupkan kembali PAUSED schedule.
     */
    public function test_checksheet_wizard_save_preserves_paused_schedule(): void
    {
        $this->schedule->forceFill([
            'lifecycle_status' => 'paused',
            'is_active' => false,
            'effective_live_from' => '2026-06-10',
        ])->save();

        $payload = [
            'checksheet_code' => 'PM-GD-001',
            'checksheet_name' => 'Guard Checksheet',
            'description' => null,
            'is_active' => true,
            ...$this->wizardPayload($this->schedulePayload()),
        ];

        // Request service langsung harus membawa session seperti request HTTP
        // produksi, karena update() menulis audit melalui ActivityLogService.
        $request = Request::create('/pm/master-checksheet', 'POST');
        $request->setLaravelSession(app('session.store'));

        app(PmChecksheetService::class)->update(
            $request,
            $this->checksheet,
            $payload,
        );

        $schedule = $this->schedule->fresh();
        $this->assertSame('paused', $schedule->lifecycle_status);
        $this->assertFalse((bool) $schedule->is_active);
        $this->assertSame('2026-06-10', $schedule->effective_live_from?->toDateString());
        $this->assertSame(0, PmScheduleDate::query()->where('pm_schedule_id', $schedule->id)->count());
    }

    /**
     * Simpan wizard checksheet tidak boleh menghidupkan kembali jadwal ENDED.
     */
    public function test_checksheet_wizard_save_cannot_revive_ended_schedule(): void
    {
        $this->endScheduleViaAuthority();

        $payload = [
            'checksheet_code' => 'PM-GD-001',
            'checksheet_name' => 'Guard Checksheet',
            'description' => null,
            'is_active' => true,
            ...$this->wizardPayload($this->schedulePayload()),
        ];

        $this->actingAs($this->admin);
        // Request langsung harus membawa session seperti request HTTP produksi,
        // karena hasil dormant dilaporkan melalui flash ke boundary admin.
        $request = Request::create('/pm/master-checksheet', 'POST');
        $request->setLaravelSession(app('session.store'));

        app(PmChecksheetService::class)->update($request, $this->checksheet, $payload);

        $this->assertScheduleStillEnded();
        $this->assertSame(
            1,
            PmSchedule::query()
                ->where('pm_checksheet_machine_id', $this->schedule->pm_checksheet_machine_id)
                ->count(),
            'Era baru tidak boleh dibuat secara implisit untuk assignment dormant.',
        );

        // Hasil dormant harus sampai ke admin, bukan hanya ke log internal.
        $dormant = $request->session()->get('dormant_assignments');
        $this->assertIsArray($dormant);
        $this->assertSame($this->schedule->pm_checksheet_machine_id, $dormant[0]['assignment_id'] ?? null);
        $this->assertSame('dormant_skipped', $dormant[0]['status'] ?? null);
        $this->assertNotEmpty($dormant[0]['instruction'] ?? null);
    }

    /**
     * TASK-006 Slice C (Correction 2): histori ENDED yang sudah soft-deleted
     * tetap terminal. Simpan wizard pada assignment hanya-ENDED-soft-deleted
     * harus tetap melaporkan dormant, tidak menghidupkan histori, dan tidak
     * membuat era baru secara implisit.
     */
    public function test_checksheet_wizard_save_keeps_soft_deleted_ended_history_dormant(): void
    {
        $this->endScheduleViaAuthority();
        $assignmentId = $this->schedule->pm_checksheet_machine_id;

        // Soft-delete era ENDED: default-scope query tidak menemuinya lagi.
        $this->schedule->delete();
        $this->assertTrue($this->schedule->fresh()->trashed());

        $payload = [
            'checksheet_code' => 'PM-GD-001',
            'checksheet_name' => 'Guard Checksheet',
            'description' => null,
            'is_active' => true,
            ...$this->wizardPayload($this->schedulePayload()),
        ];

        $request = Request::create('/pm/master-checksheet', 'POST');
        $request->setLaravelSession(app('session.store'));

        app(PmChecksheetService::class)->update($request, $this->checksheet, $payload);

        // Histori soft-deleted tetap satu-satunya era dan tetap terminal.
        $this->assertSame(
            1,
            PmSchedule::query()->withTrashed()->where('pm_checksheet_machine_id', $assignmentId)->count(),
            'Era baru tidak boleh dibuat hanya karena histori soft-deleted tak terlihat default-scope.',
        );
        $this->assertSame('ended', PmSchedule::query()->withTrashed()->where('id', $this->schedule->id)->value('lifecycle_status'));
        $this->assertFalse((bool) PmSchedule::query()->withTrashed()->where('id', $this->schedule->id)->value('is_active'));

        // Hasil dormant tetap dilaporkan ke boundary admin, bukan hilang diam-diam.
        $dormant = $request->session()->get('dormant_assignments');
        $this->assertIsArray($dormant);
        $this->assertSame($assignmentId, $dormant[0]['assignment_id'] ?? null);
        $this->assertSame('dormant_skipped', $dormant[0]['status'] ?? null);
    }
}
