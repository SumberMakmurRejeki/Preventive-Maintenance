<?php

namespace Tests\Feature\Feature\Master;

use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmExecution;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Models\User;
use App\Services\Master\PmScheduleUpdateService;
use App\Services\PM\BusinessDate;
use App\Services\PM\PmScheduleDateReconciler;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

/**
 * Test end-to-end untuk preview dan apply update jadwal PM.
 *
 * Menguji: preview tanpa tulis, klasifikasi dampak, token opaque,
 * konfirmasi, deteksi stale, transaksi + lock + rollback, proteksi baris,
 * dan idempotensi.
 */
class PmChecksheetScheduleUpdatePreviewApplyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        // Fix tanggal bisnis agar test deterministik.
        Carbon::setTestNow(Carbon::parse('2026-06-15 00:00:00', 'Asia/Jakarta'));

        $this->admin = User::query()->create([
            'name' => 'Admin PRIME',
            'username' => 'admin.preview',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $location = Location::query()->create([
            'location_code' => 'LOC-PV',
            'location_name' => 'Preview Line',
            'is_active' => true,
        ]);

        $this->machine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'MC-PV',
            'machine_name' => 'Preview Machine',
            'qr_token' => 'qr-mc-pv',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Buat checksheet + assignment + schedule langsung di database
     * (bypass validasi form request, seperti test operational window).
     */
    private function seedChecksheetWithSchedule(array $scheduleOverrides = []): PmChecksheet
    {
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-PV-001',
            'checksheet_name' => 'Preview Checksheet',
            'is_active' => true,
        ]);

        $assignment = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);

        $defaults = [
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'weekly',
            'weekly_days' => [1, 4], // Senin, Kamis
            'monthly_day' => null,
            'operational_from' => '2026-06-01',
            'start_date' => '2026-06-01',
            'generate_until' => '2026-09-01',
            'is_active' => true,
        ];

        PmSchedule::query()->create(array_merge($defaults, $scheduleOverrides));

        return $checksheet;
    }

    /**
     * Buat payload wizard dasar dengan schedule yang diberikan.
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
                        'unit' => 'Celcius',
                        'is_required' => true,
                        'is_active' => true,
                    ],
                ],
            ],
            'schedule' => $schedule,
        ];
    }

    /**
     * POST ke endpoint preview.
     */
    private function postPreview(int $checksheetId, array $wizardPayload)
    {
        return $this->actingAs($this->admin)->postJson(
            "/pm/master-checksheet/{$checksheetId}/preview-schedule-update",
            [
                'wizard_payload' => json_encode($wizardPayload, JSON_THROW_ON_ERROR),
            ],
        );
    }

    /**
     * POST ke endpoint apply.
     */
    private function postApply(int $checksheetId, array $wizardPayload, ?string $token = null, bool $confirmed = true)
    {
        return $this->actingAs($this->admin)->postJson(
            "/pm/master-checksheet/{$checksheetId}/apply-schedule-update",
            [
                'wizard_payload' => json_encode($wizardPayload, JSON_THROW_ON_ERROR),
                'token' => $token,
                'confirmed' => $confirmed,
            ],
        );
    }

    // =========================================================================
    // HARD GATE: Preview tanpa tulis ke database
    // =========================================================================

    /**
     * Pastikan preview TIDAK menulis apa pun ke database.
     * Ini adalah HARD GATE — jika gagal, Slice 2 gagal.
     */
    public function test_preview_and_apply_accept_wizard_payload_without_master_identity(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();
        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'operational_from' => '2026-07-01',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
        ]);

        $preview = $this->actingAs($this->admin)->postJson(
            "/pm/master-checksheet/{$checksheet->id}/preview-schedule-update",
            ['wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR)],
        )->assertOk();

        $this->actingAs($this->admin)->postJson(
            "/pm/master-checksheet/{$checksheet->id}/apply-schedule-update",
            [
                'wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'token' => $preview->json('token'),
                'confirmed' => true,
            ],
        )->assertOk()->assertJsonPath('status', 'applied');
    }

    public function test_preview_rejects_past_operational_from(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();
        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'operational_from' => '2026-05-01',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
        ]);

        $this->actingAs($this->admin)->postJson(
            "/pm/master-checksheet/{$checksheet->id}/preview-schedule-update",
            ['wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR)],
        )->assertStatus(422)
            ->assertJsonValidationErrors('wizard_payload.schedule.operational_from');
    }

    public function test_preview_valid_payload_stored_null_is_unresolved_without_writes(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule(['operational_from' => null]);
        $schedule = PmSchedule::query()->whereHas(
            'checksheetMachine',
            fn ($query) => $query->where('pm_checksheet_id', $checksheet->id)
        )->firstOrFail();
        PmScheduleDate::query()->create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => '2026-06-15',
            'status' => 'scheduled',
        ]);
        $before = PmScheduleDate::query()->count();

        $response = $this->postPreview($checksheet->id, $this->wizardPayload([
            'frequency_type' => 'weekly',
            'operational_from' => '2026-07-01',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
        ]));

        $response->assertOk()
            ->assertJsonPath('status', 'unresolved')
            ->assertJsonPath('unresolved', true);
        $this->assertSame($before, PmScheduleDate::query()->count());
        // Jalur unresolved tetap memakai kontrak bentuk response yang sama;
        // business_today mengikuti test-now yang dipasang di setUp.
        $this->assertSame('2026-06-15', $response->json('business_today'));
    }

    public function test_preview_does_not_write_to_database(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();

        // Jalankan reconcile awal agar ada tanggal di database.
        $this->actingAs($this->admin)->postJson(
            "/pm/master-checksheet/{$checksheet->id}/apply-schedule-update",
            [
                'checksheet_code' => 'PM-PV-001',
                'checksheet_name' => 'Preview Checksheet',
                'description' => 'Test',
                'is_active' => '1',
                'wizard_payload' => json_encode($this->wizardPayload([
                    'frequency_type' => 'weekly',
                    'operational_from' => '2026-06-01',
                    'weekly_days' => [1, 4],
                    'monthly_day' => null,
                ]), JSON_THROW_ON_ERROR),
                'token' => $this->postPreview($checksheet->id, $this->wizardPayload([
                    'frequency_type' => 'weekly',
                    'operational_from' => '2026-06-01',
                    'weekly_days' => [1, 4],
                    'monthly_day' => null,
                ]))->json('token'),
                'confirmed' => true,
            ],
        );

        // Ambil snapshot semua tabel sebelum preview.
        $beforeScheduleCount = PmSchedule::count();
        $beforeDateCount = PmScheduleDate::withTrashed()->count();
        $beforeExecutionCount = PmExecution::withTrashed()->count();
        $beforeChecksheetCount = PmChecksheet::count();

        // Jalankan preview dengan payload yang BERBEDA (daily vs weekly).
        $newPayload = $this->wizardPayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-01',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);

        $response = $this->postPreview($checksheet->id, $newPayload);
        $response->assertOk();

        // HARD GATE: semua count harus identik.
        $this->assertSame($beforeScheduleCount, PmSchedule::count(), 'Preview menulis ke pm_schedules!');
        $this->assertSame($beforeDateCount, PmScheduleDate::withTrashed()->count(), 'Preview menulis ke pm_schedule_dates!');
        $this->assertSame($beforeExecutionCount, PmExecution::withTrashed()->count(), 'Preview menulis ke pm_executions!');
        $this->assertSame($beforeChecksheetCount, PmChecksheet::count(), 'Preview menulis ke pm_checksheets!');
    }

    // =========================================================================
    // Klasifikasi preview
    // =========================================================================

    /**
     * Preview dengan frekuensi berbeda harus melaporkan tanggal baru (created).
     */
    public function test_preview_reports_created_dates_for_new_schedule(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();

        // Preview dengan daily (alih-alih weekly) -> banyak tanggal baru.
        $payload = $this->wizardPayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-01',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);

        $response = $this->postPreview($checksheet->id, $payload);
        $response->assertOk();
        $response->assertJsonPath('status', 'normal');
        $response->assertJsonPath('has_changes', true);
        $response->assertJsonPath('has_conflicts', false);
        $response->assertJsonPath('counts.created', fn ($val) => $val > 0, 'Tidak ada tanggal created yang dilaporkan.');
    }

    /**
     * Preview dengan payload identik harus melaporkan zero impact.
     */
    public function test_preview_reports_zero_impact_for_identical_schedule(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();

        // Pertama: apply agar schedule ada di DB.
        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'operational_from' => '2026-06-01',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
        ]);

        $preview1 = $this->postPreview($checksheet->id, $payload)->json();
        $this->actingAs($this->admin)->postJson(
            "/pm/master-checksheet/{$checksheet->id}/apply-schedule-update",
            [
                'checksheet_code' => 'PM-PV-001',
                'checksheet_name' => 'Preview Checksheet',
                'description' => 'Test',
                'is_active' => '1',
                'wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'token' => $preview1['token'],
                'confirmed' => true,
            ],
        );

        // Kedua: preview lagi dengan payload yang sama -> zero.
        $response = $this->postPreview($checksheet->id, $payload);
        $response->assertOk();
        $response->assertJsonPath('status', 'zero');
        $response->assertJsonPath('has_changes', false);
        $response->assertJsonPath('has_conflicts', false);
        $response->assertJsonPath('counts.created', 0);
        $response->assertJsonPath('counts.removed', 0);
    }

    /**
     * Preview dengan payload identik harus melaporkan tanggal yang dipertahankan (retained).
     * Ini memverifikasi bahwa baris aktif yang diinginkan masuk ke retained, bukan hanya zero status.
     */
    public function test_preview_reports_retained_dates_for_identical_schedule(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();

        // Apply pertama agar ada tanggal di DB.
        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'operational_from' => '2026-06-01',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
        ]);

        $preview1 = $this->postPreview($checksheet->id, $payload)->json();
        $this->actingAs($this->admin)->postJson(
            "/pm/master-checksheet/{$checksheet->id}/apply-schedule-update",
            [
                'checksheet_code' => 'PM-PV-001',
                'checksheet_name' => 'Preview Checksheet',
                'description' => 'Test',
                'is_active' => '1',
                'wizard_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'token' => $preview1['token'],
                'confirmed' => true,
            ],
        );

        // Preview lagi dengan payload yang sama.
        $response = $this->postPreview($checksheet->id, $payload);
        $response->assertOk();

        // HARUS ada tanggal retained (aktif yang diinginkan).
        $response->assertJsonPath('counts.retained', fn ($val) => $val > 0, 'Tanggal aktif yang diinginkan harus masuk retained.');
        // Tidak ada created atau removed.
        $response->assertJsonPath('counts.created', 0);
        $response->assertJsonPath('counts.removed', 0);
    }

    /**
     * Token preview harus non-empty string hex dengan panjang konsisten.
     */
    public function test_preview_returns_opaque_token(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();

        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'operational_from' => '2026-06-01',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
        ]);

        $response = $this->postPreview($checksheet->id, $payload);
        $response->assertOk();

        $token = $response->json('token');
        $this->assertNotEmpty($token, 'Token tidak boleh kosong.');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token, 'Token harus hex 64 karakter (SHA-256).');
    }

    // =========================================================================
    // Konfirmasi apply
    // =========================================================================

    /**
     * Apply tanpa 'confirmed' harus ditolak 422.
     */
    public function test_apply_rejects_without_confirmation(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();

        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'operational_from' => '2026-06-01',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
        ]);

        $response = $this->postApply($checksheet->id, $payload, token: 'dummy-token', confirmed: false);
        $response->assertStatus(422);
        $response->assertJsonPath('message', fn ($val) => str_contains($val, 'Konfirmasi'));
    }

    /**
     * Apply tanpa 'token' harus ditolak 422.
     */
    public function test_apply_rejects_without_token(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();

        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'operational_from' => '2026-06-01',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
        ]);

        $response = $this->postApply($checksheet->id, $payload, token: '', confirmed: true);
        $response->assertStatus(422);
        $response->assertJsonPath('message', fn ($val) => str_contains($val, 'Token'));
    }

    // =========================================================================
    // Deteksi stale token
    // =========================================================================

    /**
     * Token yang kedaluwarsa (DB berubah antara preview dan apply) harus ditolak.
     */
    public function test_apply_rejects_stale_token(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();

        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'operational_from' => '2026-06-01',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
        ]);

        // Preview untuk mendapatkan token.
        $previewResponse = $this->postPreview($checksheet->id, $payload);
        $previewResponse->assertOk();
        $token = $previewResponse->json('token');

        // Mutasi DB secara manual: tambah schedule_date baru.
        $schedule = PmSchedule::query()->firstOrFail();
        PmScheduleDate::query()->create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => '2099-12-31',
            'status' => 'scheduled',
        ]);

        // Simpan count sebelum apply.
        $datesBefore = PmScheduleDate::count();

        // Apply dengan token lama -> harus stale.
        $applyResponse = $this->postApply($checksheet->id, $payload, token: $token, confirmed: true);
        $applyResponse->assertStatus(409);
        $applyResponse->assertJsonPath('status', 'stale');
        $this->assertNotNull($applyResponse->json('preview'), 'Stale response harus menyertakan preview baru.');

        // Pastikan tidak ada mutasi yang diterapkan pada schedule asli.
        $this->assertSame($datesBefore, PmScheduleDate::count(), 'Apply stale seharusnya tidak mengubah tanggal.');
    }

    /**
     * Token preview harus menolak payload berbeda meskipun state DB belum berubah.
     */
    public function test_apply_rejects_payload_mismatch_with_unchanged_state(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();
        $previewPayload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'operational_from' => '2026-06-01',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
        ]);
        $differentPayload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'operational_from' => '2026-06-01',
            'weekly_days' => [2, 5],
            'monthly_day' => null,
        ]);

        $token = $this->postPreview($checksheet->id, $previewPayload)->json('token');

        $this->postApply($checksheet->id, $differentPayload, token: $token, confirmed: true)
            ->assertStatus(409)
            ->assertJsonPath('status', 'stale');
    }

    /**
     * Apply dengan token segera setelah preview harus berhasil.
     */
    public function test_apply_succeeds_with_fresh_token(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();

        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'operational_from' => '2026-06-01',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
        ]);

        // Preview -> dapatkan token.
        $previewResponse = $this->postPreview($checksheet->id, $payload);
        $previewResponse->assertOk();
        $token = $previewResponse->json('token');

        // Apply dengan token yang baru -> harus berhasil.
        $applyResponse = $this->postApply($checksheet->id, $payload, token: $token, confirmed: true);
        $applyResponse->assertOk();
        $applyResponse->assertJsonPath('status', 'applied');

        // Pastikan ada tanggal yang dibuat.
        $this->assertGreaterThan(0, PmScheduleDate::count(), 'Setelah apply, harus ada schedule dates.');

        // Penulis schedule preview/apply tetap menyimpan boolean dan status non-terminal sinkron.
        $this->assertSame('active', PmSchedule::query()->firstOrFail()->lifecycle_status);
    }

    // =========================================================================
    // Transaksi + lock + rollback
    // =========================================================================

    /**
     * Apply harus rollback jika terjadi kegagalan internal.
     * Tidak ada mutasi parsial yang boleh tersisa.
     */
    public function test_apply_rolls_back_on_failure(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();

        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'operational_from' => '2026-06-01',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
        ]);

        // Preview dan apply pertama agar ada data baseline.
        $previewResponse = $this->postPreview($checksheet->id, $payload);
        $token = $previewResponse->json('token');
        $this->postApply($checksheet->id, $payload, token: $token, confirmed: true);

        // Simpan snapshot baseline.
        $baselineDateCount = PmScheduleDate::count();
        $baselineDates = PmScheduleDate::query()
            ->pluck('scheduled_date')
            ->map(fn ($d) => $d->toDateString())
            ->sort()
            ->values()
            ->all();

        // Ubah payload agar menghasilkan perubahan (daily alih-alih weekly).
        $newPayload = $this->wizardPayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-01',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);

        // Preview baru dengan payload yang berubah.
        $newPreview = $this->postPreview($checksheet->id, $newPayload);
        $newToken = $newPreview->json('token');

        // Pasang listener yang melempar exception saat PmScheduleDate dibuat.
        Event::listen(
            'eloquent.creating: '.PmScheduleDate::class,
            fn () => throw new \RuntimeException('Inject kegagalan untuk test rollback.'),
        );

        // Apply harus gagal karena exception.
        $applyResponse = $this->postApply($checksheet->id, $newPayload, token: $newToken, confirmed: true);
        $applyResponse->assertStatus(500);

        // Pastikan TIDAK ada perubahan parsial: count dan tanggal harus identik.
        $this->assertSame($baselineDateCount, PmScheduleDate::count(), 'Rollback gagal: jumlah tanggal berubah.');

        $currentDates = PmScheduleDate::query()
            ->pluck('scheduled_date')
            ->map(fn ($d) => $d->toDateString())
            ->sort()
            ->values()
            ->all();

        $this->assertSame($baselineDates, $currentDates, 'Rollback gagal: tanggal berubah setelah kegagalan.');
    }

    // =========================================================================
    // Proteksi baris (protected rows)
    // =========================================================================

    /**
     * Tanggal dengan status in_progress dan eksekusi aktif harus dipertahankan.
     */
    public function test_apply_preserves_protected_active_execution(): void
    {
        // Seed dengan start_date yang berbeda agar tanggal tidak tumpang tindih.
        $checksheet = $this->seedChecksheetWithSchedule([
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-20',
            'start_date' => '2026-06-20',
            'generate_until' => '2026-06-22',
        ]);

        // Apply pertama agar ada tanggal di DB.
        $payload1 = $this->wizardPayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-20',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);
        $preview1 = $this->postPreview($checksheet->id, $payload1)->json();
        $this->postApply($checksheet->id, $payload1, token: $preview1['token'], confirmed: true);

        // Ambil satu schedule_date dan buat eksekusi aktif.
        $scheduleDate = PmScheduleDate::query()->firstOrFail();
        $scheduleDate->update(['status' => 'in_progress']);

        $execution = PmExecution::query()->create([
            'pm_schedule_date_id' => $scheduleDate->id,
            'machine_id' => $this->machine->id,
            'status' => 'in_progress',
        ]);

        // Ubah payload ke rentang yang TIDAK mencakup tanggal tersebut.
        $payload2 = $this->wizardPayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-07-15',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);
        $preview2 = $this->postPreview($checksheet->id, $payload2)->json();
        $this->postApply($checksheet->id, $payload2, token: $preview2['token'], confirmed: true);

        // Tanggal protected harus tetap ada (tidak dihapus, tidak force-delete).
        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $scheduleDate->id,
            'status' => 'in_progress',
        ]);
        $this->assertDatabaseHas('pm_executions', [
            'id' => $execution->id,
            'status' => 'in_progress',
        ]);
    }

    /**
     * Tanggal soft-deleted yang punya eksekusi soft-deleted harus dipertahankan.
     */
    public function test_apply_preserves_protected_soft_deleted_execution(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule([
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-20',
            'start_date' => '2026-06-20',
            'generate_until' => '2026-06-22',
        ]);

        // Apply pertama.
        $payload1 = $this->wizardPayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-20',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);
        $preview1 = $this->postPreview($checksheet->id, $payload1)->json();
        $this->postApply($checksheet->id, $payload1, token: $preview1['token'], confirmed: true);

        // Ambil satu schedule_date, buat eksekusi, lalu soft-delete keduanya.
        $scheduleDate = PmScheduleDate::query()->firstOrFail();
        $scheduleDate->update(['status' => 'approved']);

        $execution = PmExecution::query()->create([
            'pm_schedule_date_id' => $scheduleDate->id,
            'machine_id' => $this->machine->id,
            'status' => 'approved',
        ]);

        // Soft-delete execution dulu (karena FK), lalu schedule_date.
        $execution->delete();
        $scheduleDate->delete();

        // Ubah payload ke rentang berbeda.
        $payload2 = $this->wizardPayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-07-15',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);
        $preview2 = $this->postPreview($checksheet->id, $payload2)->json();
        $this->postApply($checksheet->id, $payload2, token: $preview2['token'], confirmed: true);

        // Tanggal soft-deleted dengan eksekusi soft-deleted tetap ada (protected).
        $this->assertSoftDeleted('pm_schedule_dates', ['id' => $scheduleDate->id]);
        $this->assertSoftDeleted('pm_executions', ['id' => $execution->id]);
    }

    /**
     * Status 'in_progress' tidak boleh berubah oleh apply.
     */
    public function test_apply_preserves_in_progress_status(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule([
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-20',
            'start_date' => '2026-06-20',
            'generate_until' => '2026-06-22',
        ]);

        // Apply pertama.
        $payload1 = $this->wizardPayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-20',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);
        $preview1 = $this->postPreview($checksheet->id, $payload1)->json();
        $this->postApply($checksheet->id, $payload1, token: $preview1['token'], confirmed: true);

        // Ubah satu tanggal ke in_progress.
        $scheduleDate = PmScheduleDate::query()->firstOrFail();
        $scheduleDate->update(['status' => 'in_progress']);

        // Buat eksekusi agar protected.
        PmExecution::query()->create([
            'pm_schedule_date_id' => $scheduleDate->id,
            'machine_id' => $this->machine->id,
            'status' => 'in_progress',
        ]);

        // Apply ulang dengan payload berbeda.
        $payload2 = $this->wizardPayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-07-15',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);
        $preview2 = $this->postPreview($checksheet->id, $payload2)->json();
        $this->postApply($checksheet->id, $payload2, token: $preview2['token'], confirmed: true);

        // Status harus tetap in_progress.
        $scheduleDate->refresh();
        $this->assertSame('in_progress', $scheduleDate->status);
    }

    /**
     * Status 'waiting_review' tidak boleh berubah oleh apply.
     */
    public function test_apply_preserves_waiting_review_status(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule([
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-20',
            'start_date' => '2026-06-20',
            'generate_until' => '2026-06-22',
        ]);

        $payload1 = $this->wizardPayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-20',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);
        $preview1 = $this->postPreview($checksheet->id, $payload1)->json();
        $this->postApply($checksheet->id, $payload1, token: $preview1['token'], confirmed: true);

        $scheduleDate = PmScheduleDate::query()->firstOrFail();
        $scheduleDate->update(['status' => 'waiting_review']);

        // Buat eksekusi agar protected. Status eksekusi: 'in_progress' (valid CHECK).
        // Status schedule_date 'waiting_review' saja sudah immutable (bukan MUTABLE_STATUSES),
        // namun eksekusi memastikan reconciler tidak menghapus baris ini.
        PmExecution::query()->create([
            'pm_schedule_date_id' => $scheduleDate->id,
            'machine_id' => $this->machine->id,
            'status' => 'in_progress',
        ]);

        $payload2 = $this->wizardPayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-07-15',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);
        $preview2 = $this->postPreview($checksheet->id, $payload2)->json();
        $this->postApply($checksheet->id, $payload2, token: $preview2['token'], confirmed: true);

        $scheduleDate->refresh();
        $this->assertSame('waiting_review', $scheduleDate->status);
    }

    /**
     * Status 'approved' tidak boleh berubah oleh apply.
     */
    public function test_apply_preserves_approved_status(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule([
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-20',
            'start_date' => '2026-06-20',
            'generate_until' => '2026-06-22',
        ]);

        $payload1 = $this->wizardPayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-06-20',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);
        $preview1 = $this->postPreview($checksheet->id, $payload1)->json();
        $this->postApply($checksheet->id, $payload1, token: $preview1['token'], confirmed: true);

        $scheduleDate = PmScheduleDate::query()->firstOrFail();
        $scheduleDate->update(['status' => 'approved']);

        // Buat eksekusi agar protected.
        PmExecution::query()->create([
            'pm_schedule_date_id' => $scheduleDate->id,
            'machine_id' => $this->machine->id,
            'status' => 'approved',
        ]);

        $payload2 = $this->wizardPayload([
            'frequency_type' => 'daily',
            'operational_from' => '2026-07-15',
            'weekly_days' => [],
            'monthly_day' => null,
        ]);
        $preview2 = $this->postPreview($checksheet->id, $payload2)->json();
        $this->postApply($checksheet->id, $payload2, token: $preview2['token'], confirmed: true);

        $scheduleDate->refresh();
        $this->assertSame('approved', $scheduleDate->status);
    }

    // =========================================================================
    // Idempotensi
    // =========================================================================

    /**
     * Apply dua kali berturut-turut (dengan token baru masing-masing) harus
     * menghasilkan hasil yang idempotent: tidak ada duplikat, tidak destruktif.
     */
    public function test_repeated_apply_is_idempotent(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();

        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'operational_from' => '2026-06-01',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
        ]);

        // Apply pertama.
        $preview1 = $this->postPreview($checksheet->id, $payload)->json();
        $apply1 = $this->postApply($checksheet->id, $payload, token: $preview1['token'], confirmed: true);
        $apply1->assertOk();
        $apply1->assertJsonPath('status', 'applied');

        $datesAfterFirst = PmScheduleDate::query()
            ->pluck('scheduled_date')
            ->map(fn ($d) => $d->toDateString())
            ->sort()
            ->values()
            ->all();
        $countAfterFirst = PmScheduleDate::count();

        // Apply kedua dengan token baru dari preview baru.
        $preview2 = $this->postPreview($checksheet->id, $payload)->json();
        $apply2 = $this->postApply($checksheet->id, $payload, token: $preview2['token'], confirmed: true);
        $apply2->assertOk();
        $apply2->assertJsonPath('status', 'applied');

        // Tidak ada duplikat: count dan tanggal harus identik.
        $this->assertSame($countAfterFirst, PmScheduleDate::count(), 'Idempotensi gagal: jumlah tanggal berubah.');

        $datesAfterSecond = PmScheduleDate::query()
            ->pluck('scheduled_date')
            ->map(fn ($d) => $d->toDateString())
            ->sort()
            ->values()
            ->all();

        $this->assertSame($datesAfterFirst, $datesAfterSecond, 'Idempotensi gagal: tanggal berubah.');
    }

    // =========================================================================
    // Multi-machine preview parity
    // =========================================================================

    /**
     * Preview harus menghitung creation per schedule, bukan global.
     *
     * Skenario: 2 mesin, 2 schedule, desired date sama.
     * Machine A sudah punya occurrence pada desired date.
     * Machine B belum punya occurrence pada desired date.
     * Preview harus melaporkan creation untuk Machine B.
     *
     * Bug sebelumnya: global existingActiveDates map menyebabkan preview
     * menganggap tanggal sudah ada untuk seluruh checksheet.
     */
    public function test_preview_reports_creation_per_schedule_not_globally(): void
    {
        // Buat mesin kedua di lokasi yang sama.
        $machineB = Machine::query()->create([
            'location_id' => $this->machine->location_id,
            'machine_code' => 'MC-PV-B',
            'machine_name' => 'Preview Machine B',
            'qr_token' => 'qr-mc-pv-b',
            'is_active' => true,
        ]);

        // Buat checksheet dengan 2 assignment.
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-PV-MULTI',
            'checksheet_name' => 'Multi Machine Checksheet',
            'is_active' => true,
        ]);

        $assignmentA = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);

        $assignmentB = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $machineB->id,
        ]);

        // Kedua schedule: weekly Senin+Kamis, range sama.
        $scheduleDefaults = [
            'frequency_type' => 'weekly',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
            'operational_from' => '2026-06-01',
            'start_date' => '2026-06-01',
            'generate_until' => '2026-06-30',
            'is_active' => true,
        ];

        $scheduleA = PmSchedule::query()->create(
            array_merge($scheduleDefaults, ['pm_checksheet_machine_id' => $assignmentA->id])
        );

        $scheduleB = PmSchedule::query()->create(
            array_merge($scheduleDefaults, ['pm_checksheet_machine_id' => $assignmentB->id])
        );

        // Machine A: sudah punya occurrence pada 2026-06-18 (Kamis).
        PmScheduleDate::query()->create([
            'pm_schedule_id' => $scheduleA->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => '2026-06-18',
            'status' => 'scheduled',
        ]);

        // Machine B: TIDAK punya occurrence pada 2026-06-18.

        // Payload: desired dates mencakup 2026-06-18.
        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'operational_from' => '2026-06-01',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
        ]);

        $response = $this->postPreview($checksheet->id, $payload);
        $response->assertOk();

        $preview = $response->json();

        // 2026-06-18 harus ada di created karena Machine B belum punya.
        // Bug: global map menganggap tanggal sudah ada -> created kosong untuk tanggal ini.
        $this->assertContains(
            '2026-06-18',
            $preview['impact']['created'],
            'Preview harus melaporkan 2026-06-18 sebagai created untuk Machine B yang belum punya occurrence.'
        );

        // Verifikasi parity: apply harus membuat jumlah occurrence yang konsisten.
        $applyResult = $this->postApply($checksheet->id, $payload, token: $preview['token'], confirmed: true);
        $applyResult->assertOk();

        // Setelah apply, Machine B harus punya occurrence pada 2026-06-18.
        // Catatan: gunakan whereDate() karena SQLite menyimpan DATE dengan suffix waktu
        // ('2026-06-18 00:00:00'), sehingga where() string literal tidak cocok.
        $this->assertTrue(
            PmScheduleDate::query()
                ->where('pm_schedule_id', $scheduleB->id)
                ->whereDate('scheduled_date', '2026-06-18')
                ->exists(),
            'Apply harus membuat occurrence 2026-06-18 untuk Machine B.'
        );

        // Machine A tetap hanya 1 occurrence (tidak duplikat).
        $this->assertSame(
            1,
            PmScheduleDate::query()
                ->where('pm_schedule_id', $scheduleA->id)
                ->whereDate('scheduled_date', '2026-06-18')
                ->count(),
            'Machine A tidak boleh duplikat occurrence 2026-06-18.'
        );
    }

    // =========================================================================
    // CORRECTION A: Fingerprint harus mendeteksi perubahan state dalam-row
    // =========================================================================

    /**
     * Regression: perubahan status occurrence (scheduled → approved) tanpa
     * menambah/menghapus row harus membuat token stale.
     */
    public function test_fingerprint_detects_status_change_without_row_count_change(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();
        $schedule = PmSchedule::first();

        // Buat 2 occurrence dengan status scheduled
        $date1 = PmScheduleDate::create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => '2026-06-16',
            'status' => 'scheduled',
        ]);
        $date2 = PmScheduleDate::create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => '2026-06-19',
            'status' => 'scheduled',
        ]);

        $service = app(PmScheduleUpdateService::class);
        $businessToday = BusinessDate::today();

        // Token A: fingerprint awal
        $tokenA = $service->fingerprint($checksheet, $businessToday);

        // Ubah status date1 dari scheduled → approved (row count tetap 2)
        $date1->update(['status' => 'approved']);

        // Token B: fingerprint setelah perubahan status
        $tokenB = $service->fingerprint($checksheet, $businessToday);

        // Token harus berbeda karena status berubah
        $this->assertNotEquals($tokenA, $tokenB, 'Fingerprint harus berubah ketika status occurrence berubah');
    }

    /**
     * Regression: penambahan execution pada existing date tanpa mengubah
     * schedule-date row count harus membuat token stale.
     */
    public function test_fingerprint_detects_execution_addition_without_date_count_change(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();
        $schedule = PmSchedule::first();

        // Buat 1 occurrence
        $date1 = PmScheduleDate::create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => '2026-06-16',
            'status' => 'scheduled',
        ]);

        $service = app(PmScheduleUpdateService::class);
        $businessToday = BusinessDate::today();

        // Token A: fingerprint tanpa execution
        $tokenA = $service->fingerprint($checksheet, $businessToday);

        // Tambahkan execution pada date1 (date row count tetap 1)
        PmExecution::create([
            'pm_schedule_date_id' => $date1->id,
            'machine_id' => $this->machine->id,
            'operator_id' => $this->admin->id,
            'operator_name_snapshot' => $this->admin->name,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        // Token B: fingerprint setelah penambahan execution
        $tokenB = $service->fingerprint($checksheet, $businessToday);

        // Token harus berbeda karena execution bertambah
        $this->assertNotEquals($tokenA, $tokenB, 'Fingerprint harus berubah ketika execution ditambahkan');
    }

    /**
     * Regression: soft-delete execution tanpa mengubah schedule-date row count
     * harus membuat token stale.
     */
    public function test_fingerprint_detects_execution_soft_delete_without_date_count_change(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();
        $schedule = PmSchedule::first();

        // Buat 1 occurrence + 1 execution
        $date1 = PmScheduleDate::create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => '2026-06-16',
            'status' => 'scheduled',
        ]);
        $execution = PmExecution::create([
            'pm_schedule_date_id' => $date1->id,
            'machine_id' => $this->machine->id,
            'operator_id' => $this->admin->id,
            'operator_name_snapshot' => $this->admin->name,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $service = app(PmScheduleUpdateService::class);
        $businessToday = BusinessDate::today();

        // Token A: fingerprint dengan execution aktif
        $tokenA = $service->fingerprint($checksheet, $businessToday);

        // Soft-delete execution (date row count tetap 1, execution masih ada di DB dengan deleted_at)
        $execution->delete();

        // Token B: fingerprint setelah execution di-soft-delete
        $tokenB = $service->fingerprint($checksheet, $businessToday);

        // Token harus berbeda karena execution state berubah (soft-deleted)
        $this->assertNotEquals($tokenA, $tokenB, 'Fingerprint harus berubah ketika execution di-soft-delete');
    }

    /**
     * Regression: apply() harus dapat membuat jadwal baru untuk assignment yang
     * belum memiliki schedule aktif. persistScheduleConfig() melakukan upsert
     * (updateOrCreate) sehingga assignment tanpa jadwal aktif tetap diproses.
     */
    public function test_apply_creates_schedule_for_assignment_without_active_schedule(): void
    {
        // Buat checksheet + assignment TANPA schedule aktif
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-LOCK-TEST',
            'checksheet_name' => 'Lock Test Checksheet',
            'is_active' => true,
        ]);

        PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);

        // Pastikan tidak ada schedule aktif untuk machine ini
        $this->assertDatabaseMissing('pm_schedules', [
            'pm_checksheet_machine_id' => $checksheet->machineAssignments()->first()->id,
            'is_active' => true,
        ]);

        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'weekly_days' => [1, 3, 5],
            'monthly_day' => null,
            'operational_from' => '2026-06-15',
            'start_date' => '2026-06-15',
            'generate_until' => '2026-09-15',
        ]);

        // Preview harus berhasil (meski belum ada schedule aktif)
        $preview = $this->actingAs($this->admin)
            ->postJson("/pm/master-checksheet/{$checksheet->id}/preview-schedule-update", [
                'wizard_payload' => $payload,
            ]);

        // Preview returns 'zero' karena belum ada schedule aktif untuk dibandingkan,
        // tetapi token fingerprint tetap dikembalikan agar apply dapat berjalan.
        $preview->assertOk();
        $this->assertContains($preview->json('status'), ['ready', 'zero']);
        $token = $preview->json('token');
        $this->assertNotEmpty($token, 'Preview harus selalu mengembalikan token fingerprint');
        // Apply harus berhasil dan membuat schedule baru
        $apply = $this->actingAs($this->admin)
            ->postJson("/pm/master-checksheet/{$checksheet->id}/apply-schedule-update", [
                'wizard_payload' => $payload,
                'token' => $token,
                'confirmed' => true,
            ]);

        $apply->assertOk();
        $apply->assertJsonPath('status', 'applied');

        // Verifikasi schedule baru dibuat untuk assignment
        $assignment = $checksheet->machineAssignments()->first();
        $this->assertDatabaseHas('pm_schedules', [
            'pm_checksheet_machine_id' => $assignment->id,
            'is_active' => true,
            'frequency_type' => 'weekly',
        ]);
    }

    // =========================================================================
    // SLICE C: Semantik current era (dormant, fingerprint, sibling isolation)
    // =========================================================================

    /**
     * Assignment dengan hanya era ENDED dilaporkan dormant secara terstruktur
     * pada preview dan apply, tanpa membuat era baru dan tanpa memutasi histori.
     */
    public function test_ended_only_assignment_is_reported_dormant_at_preview_and_apply(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule([
            'lifecycle_status' => 'ended',
            'is_active' => false,
        ]);
        $assignment = $checksheet->machineAssignments()->first();
        $endedSchedule = PmSchedule::query()->first();

        // Occurrence historis pada era ENDED harus tetap utuh setelah apply.
        $historicDate = PmScheduleDate::create([
            'pm_schedule_id' => $endedSchedule->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => '2026-06-16',
            'status' => 'approved',
        ]);

        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'weekly_days' => [2, 5],
            'monthly_day' => null,
            'operational_from' => '2026-06-15',
            'start_date' => '2026-06-15',
            'generate_until' => '2026-09-15',
        ]);

        $preview = $this->postPreview($checksheet->id, $payload);
        $preview->assertOk();

        // Era ENDED tidak boleh diklasifikasi sebagai impact current.
        $this->assertNotContains('2026-06-16', $preview->json('impact.removed'));
        $this->assertNotContains('2026-06-16', $preview->json('impact.protected'));
        $this->assertNotContains('2026-06-16', $preview->json('impact.retained'));

        // Dormant harus terlihat sebagai hasil terstruktur pada boundary preview.
        $this->assertSame(
            [
                [
                    'assignment_id' => $assignment->id,
                    'status' => 'dormant_skipped',
                    'reason' => 'Assignment hanya memiliki era Schedule berstatus ended.',
                    'instruction' => 'Era operasional baru hanya dapat dibuat melalui recommission yang eksplisit.',
                ],
            ],
            $preview->json('dormant_assignments'),
        );

        $apply = $this->postApply($checksheet->id, $payload, $preview->json('token'));
        $apply->assertOk();
        $apply->assertJsonPath('status', 'applied');

        // Tidak ada era baru yang dibuat secara implisit untuk assignment dormant.
        $this->assertSame(
            1,
            PmSchedule::query()->where('pm_checksheet_machine_id', $assignment->id)->count(),
        );
        $this->assertSame('ended', $endedSchedule->fresh()->lifecycle_status);
        $this->assertSame('approved', $historicDate->fresh()->status);
        $this->assertNull($historicDate->fresh()->deleted_at);

        // Dormant juga terlihat sebagai hasil terstruktur pada boundary apply.
        $this->assertSame('dormant_skipped', $apply->json('dormant_assignments.0.status'));
        $this->assertSame($assignment->id, $apply->json('dormant_assignments.0.assignment_id'));
    }

    /**
     * Assignment dormant tidak boleh memblokir update sibling pada checksheet yang sama.
     */
    public function test_dormant_assignment_does_not_block_sibling_assignment_update(): void
    {
        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-SIBLING',
            'checksheet_name' => 'Sibling Isolation Checksheet',
            'is_active' => true,
        ]);

        // Assignment A: era current ACTIVE yang sah untuk dimutasi.
        $assignmentA = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
        ]);
        $scheduleA = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignmentA->id,
            'frequency_type' => 'weekly',
            'weekly_days' => [1, 4],
            'operational_from' => '2026-06-01',
            'start_date' => '2026-06-01',
            'generate_until' => '2026-09-01',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'effective_live_from' => '2026-06-01',
        ]);

        // Assignment B: hanya era ENDED (dormant) dengan histori terlindungi.
        $machineB = Machine::query()->create([
            'location_id' => $this->machine->location_id,
            'machine_code' => 'MC-SIB',
            'machine_name' => 'Sibling Dormant Machine',
            'qr_token' => 'qr-mc-sib',
            'is_active' => true,
            'lifecycle_status' => 'retired',
        ]);
        $assignmentB = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $machineB->id,
        ]);
        $scheduleB = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignmentB->id,
            'frequency_type' => 'daily',
            'operational_from' => '2026-05-01',
            'start_date' => '2026-05-01',
            'generate_until' => '2026-06-01',
            'is_active' => false,
            'lifecycle_status' => 'ended',
        ]);
        $historicDateB = PmScheduleDate::create([
            'pm_schedule_id' => $scheduleB->id,
            'machine_id' => $machineB->id,
            'scheduled_date' => '2026-05-04',
            'status' => 'approved',
        ]);

        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'weekly_days' => [2, 5],
            'monthly_day' => null,
            'operational_from' => '2026-06-15',
            'start_date' => '2026-06-15',
            'generate_until' => '2026-09-15',
        ]);

        $preview = $this->postPreview($checksheet->id, $payload);
        $preview->assertOk();

        // Dormant hanya milik assignment B; A tetap diklasifikasi normal.
        $this->assertSame($assignmentB->id, $preview->json('dormant_assignments.0.assignment_id'));
        $this->assertSame('dormant_skipped', $preview->json('dormant_assignments.0.status'));
        $this->assertCount(1, $preview->json('dormant_assignments'));
        $this->assertNotContains('2026-05-04', $preview->json('impact.removed'));

        $apply = $this->postApply($checksheet->id, $payload, $preview->json('token'));
        $apply->assertOk();
        $apply->assertJsonPath('status', 'applied');

        // Assignment A berubah sesuai payload; era B dan histori B tetap utuh.
        $this->assertSame('weekly', $scheduleA->fresh()->frequency_type);
        $this->assertSame([2, 5], $scheduleA->fresh()->weekly_days);
        $this->assertSame('ended', $scheduleB->fresh()->lifecycle_status);
        $this->assertSame('daily', $scheduleB->fresh()->frequency_type);
        $this->assertSame('approved', $historicDateB->fresh()->status);
        $this->assertNull($historicDateB->fresh()->deleted_at);
        $this->assertSame(
            1,
            PmSchedule::query()->where('pm_checksheet_machine_id', $assignmentB->id)->count(),
            'Assignment dormant tidak boleh mendapat era baru secara implisit.',
        );
        $this->assertGreaterThan(
            0,
            PmScheduleDate::query()->where('pm_schedule_id', $scheduleA->id)->count(),
            'Assignment sibling aktif harus tetap direkonsiliasi.',
        );
    }

    /**
     * Fingerprint harus mengabaikan baris era terminal dan tetap sensitif
     * terhadap perubahan pada era current.
     */
    public function test_fingerprint_ignores_terminal_era_rows_but_detects_current_era_change(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule([
            'lifecycle_status' => 'active',
            'effective_live_from' => '2026-06-01',
        ]);
        $assignment = $checksheet->machineAssignments()->first();

        // Era terminal pada assignment yang sama.
        $endedSchedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $assignment->id,
            'frequency_type' => 'daily',
            'operational_from' => '2026-01-01',
            'start_date' => '2026-01-01',
            'generate_until' => '2026-02-01',
            'is_active' => false,
            'lifecycle_status' => 'ended',
        ]);

        $service = app(PmScheduleUpdateService::class);
        $businessToday = BusinessDate::today();
        $tokenA = $service->fingerprint($checksheet, $businessToday);

        // Penambahan occurrence pada era terminal tidak boleh mengubah token.
        PmScheduleDate::create([
            'pm_schedule_id' => $endedSchedule->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => '2026-01-02',
            'status' => 'approved',
        ]);
        $tokenB = $service->fingerprint($checksheet, $businessToday);
        $this->assertSame($tokenA, $tokenB, 'Fingerprint tidak boleh bergantung pada era terminal.');

        // Perubahan pada era current tetap harus terdeteksi.
        $current = PmSchedule::query()->currentEra()->sole();
        PmScheduleDate::create([
            'pm_schedule_id' => $current->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => '2026-06-16',
            'status' => 'scheduled',
        ]);
        $tokenC = $service->fingerprint($checksheet, $businessToday);
        $this->assertNotSame($tokenB, $tokenC, 'Fingerprint harus tetap mendeteksi perubahan era current.');
    }

    /**
     * Era current PAUSED tidak boleh diresume atau dimaterialisasi secara
     * implisit oleh apply; hanya transition lifecycle eksplisit yang boleh
     * mengubahnya.
     */
    public function test_paused_current_era_remains_paused_and_unmaterialized(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule([
            'lifecycle_status' => 'paused',
            'is_active' => false,
            'effective_live_from' => '2026-06-01',
        ]);
        $assignment = $checksheet->machineAssignments()->first();
        $schedule = PmSchedule::query()->first();

        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'weekly_days' => [2, 5],
            'monthly_day' => null,
            'operational_from' => '2026-06-15',
            'start_date' => '2026-06-15',
            'generate_until' => '2026-09-15',
        ]);

        $preview = $this->postPreview($checksheet->id, $payload);
        $preview->assertOk();

        // Era paused tidak boleh muncul sebagai impact yang akan dimutasi.
        $this->assertSame([], $preview->json('impact.created'));
        $this->assertSame([], $preview->json('impact.removed'));

        $apply = $this->postApply($checksheet->id, $payload, $preview->json('token'));
        $apply->assertOk();
        $apply->assertJsonPath('status', 'applied');

        $fresh = $schedule->fresh();
        $this->assertSame('paused', $fresh->lifecycle_status);
        $this->assertFalse((bool) $fresh->is_active);
        $this->assertSame('2026-06-01', $fresh->effective_live_from?->toDateString());
        // Konfigurasi boleh diperbarui, tetapi tanpa materialisasi occurrence.
        $this->assertSame([2, 5], $fresh->weekly_days);
        $this->assertSame(0, PmScheduleDate::query()->where('pm_schedule_id', $schedule->id)->count());
        $this->assertSame(1, PmSchedule::query()->where('pm_checksheet_machine_id', $assignment->id)->count());
    }

    /**
     * TASK-006 Slice C: hasil dormant harus terlihat di halaman admin, bukan
     * hanya pada response JSON internal. Flash yang diset writer dirender
     * menjadi peringatan yang menyebut kode/nama mesin beserta instruksinya.
     */
    public function test_dormant_assignment_result_is_visible_on_admin_checksheet_page(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();
        $assignment = $checksheet->machineAssignments()->first();

        $response = $this->actingAs($this->admin)
            ->withSession([
                'dormant_assignments' => [
                    [
                        'assignment_id' => $assignment->id,
                        'status' => 'dormant_skipped',
                        'reason' => 'Era schedule terakhir berstatus ended.',
                        'instruction' => 'Gunakan recommission eksplisit untuk memulai era baru.',
                    ],
                ],
            ])
            ->get(route('master-checksheet.show', $checksheet->id));

        $response->assertOk();
        $response->assertSee('dormant', false);
        $response->assertSee($this->machine->machine_code, false);
        $response->assertSee($this->machine->machine_name, false);
        $response->assertSee('Era schedule terakhir berstatus ended.', false);
        $response->assertSee('Gunakan recommission eksplisit untuk memulai era baru.', false);
    }

    /**
     * Halaman admin tanpa hasil dormant tidak boleh memunculkan peringatan.
     */
    public function test_admin_checksheet_page_has_no_dormant_warning_without_result(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule();

        $response = $this->actingAs($this->admin)
            ->get(route('master-checksheet.show', $checksheet->id));

        $response->assertOk();
        $response->assertDontSee('tidak diubah (dormant)', false);
    }

    /**
     * TASK-006 Slice C (Correction 2): histori ENDED yang sudah SOFT-DELETED
     * tetap merupakan histori terminal. Assignment hanya-ENDED-soft-deleted
     * tanpa era current harus tetap dilaporkan dormant: preview tidak boleh
     * membuat era aktif implisit, dan apply tidak boleh membuat era baru
     * hanya karena default-scope query tidak melihat baris yang soft-deleted.
     */
    public function test_soft_deleted_ended_only_assignment_is_reported_dormant_without_implicit_era(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule([
            'lifecycle_status' => 'ended',
            'is_active' => false,
        ]);
        $assignment = $checksheet->machineAssignments()->first();
        $endedSchedule = PmSchedule::query()->withTrashed()->where('pm_checksheet_machine_id', $assignment->id)->firstOrFail();

        // Histori ENDED di-soft-delete: default-scope query tidak menemuinya.
        $endedSchedule->delete();

        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'weekly_days' => [2, 5],
            'monthly_day' => null,
            'operational_from' => '2026-06-15',
            'start_date' => '2026-06-15',
            'generate_until' => '2026-09-15',
        ]);
        $preview = $this->postPreview($checksheet->id, $payload);
        $preview->assertOk();
        $this->assertSame(
            [
                [
                    'assignment_id' => $assignment->id,
                    'status' => 'dormant_skipped',
                    'reason' => 'Assignment hanya memiliki era Schedule berstatus ended.',
                    'instruction' => 'Era operasional baru hanya dapat dibuat melalui recommission yang eksplisit.',
                ],
            ],
            $preview->json('dormant_assignments'),
        );

        $apply = $this->postApply($checksheet->id, $payload, $preview->json('token'));
        $apply->assertOk();
        $apply->assertJsonPath('status', 'applied');

        // Era soft-deleted tetap utuh dan tetap terminal; tidak dihidupkan,
        // tidak di-restore, tidak dimutasi.
        $this->assertSame(1, PmSchedule::query()->withTrashed()->where('pm_checksheet_machine_id', $assignment->id)->count());
        $this->assertSame(1, PmSchedule::query()->withTrashed()->where('pm_checksheet_machine_id', $assignment->id)->onlyTrashed()->count());
        $this->assertSame('ended', PmSchedule::query()->withTrashed()->where('id', $endedSchedule->id)->value('lifecycle_status'));
        $this->assertFalse((bool) PmSchedule::query()->withTrashed()->where('id', $endedSchedule->id)->value('is_active'));
        $this->assertSame('dormant_skipped', $apply->json('dormant_assignments.0.status'));
    }

    // =========================================================================
    // SLICE C (Correction 3): dampak reconciliation tunggal pada apply
    // =========================================================================

    /**
     * Apply harus melaporkan dampak reconciliation yang benar-benar terjadi.
     *
     * Sebelum koreksi, persistScheduleConfig() mereconcile schedule lebih dulu
     * sehingga loop agregat apply() menjadi reconcile kedua: mutasi pertama
     * terbuang dan counts.created dilaporkan 0 meski tanggal nyata tercipta.
     */
    public function test_apply_reports_actual_reconciliation_impact_once(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule([
            'frequency_type' => 'weekly',
            'weekly_days' => [1, 4],
            'operational_from' => '2026-06-01',
            'start_date' => '2026-06-01',
            'generate_until' => '2026-09-01',
        ]);
        $schedule = PmSchedule::query()->first();

        // Apply awal: materialisasi tanggal untuk konfigurasi tersimpan.
        $initialPayload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'weekly_days' => [1, 4],
            'monthly_day' => null,
            'operational_from' => '2026-06-01',
            'start_date' => '2026-06-01',
            'generate_until' => '2026-09-01',
        ]);
        $initial = $this->postApply(
            $checksheet->id,
            $initialPayload,
            $this->postPreview($checksheet->id, $initialPayload)->json('token'),
        );
        $initial->assertOk();
        $this->assertGreaterThan(0, PmScheduleDate::query()->where('pm_schedule_id', $schedule->id)->count());

        // Perubahan nyata: hari minggu digeser, sebagian tanggal harus dibuat ulang.
        $changedPayload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'weekly_days' => [2, 5],
            'monthly_day' => null,
            'operational_from' => '2026-06-01',
            'start_date' => '2026-06-01',
            'generate_until' => '2026-09-01',
        ]);
        $preview = $this->postPreview($checksheet->id, $changedPayload);
        $preview->assertOk();
        $expectedCreated = $preview->json('counts.created');
        $expectedRemoved = $preview->json('counts.removed');
        $this->assertGreaterThan(0, $expectedCreated, 'Preview harus melaporkan tanggal baru yang akan dibuat.');

        $apply = $this->postApply($checksheet->id, $changedPayload, $preview->json('token'));
        $apply->assertOk();
        $apply->assertJsonPath('status', 'applied');
        $this->assertSame(
            $expectedCreated,
            $apply->json('counts.created'),
            'Apply harus melaporkan dampak nyata, bukan nol akibat reconcile ganda.',
        );
        $this->assertSame($expectedRemoved, $apply->json('counts.removed'));
        $this->assertTrue($apply->json('has_changes'));

        $createdDates = collect($preview->json('impact.created'));
        $actualCreatedDates = PmScheduleDate::query()
            ->where('pm_schedule_id', $schedule->id)
            ->get()
            ->map(fn (PmScheduleDate $date): string => $date->scheduled_date->toDateString());
        $this->assertSame(
            $expectedCreated,
            $actualCreatedDates->intersect($createdDates)->count(),
            'Tanggal yang dilaporkan created preview harus benar-benar termaterialisasi.',
        );

        // Apply ulang tanpa perubahan harus melaporkan zero change secara akurat.
        $repeat = $this->postApply($checksheet->id, $changedPayload, $apply->json('token'));
        $repeat->assertOk();
        $this->assertSame(0, $repeat->json('counts.created'));
        $this->assertSame(0, $repeat->json('counts.removed'));
        $this->assertFalse($repeat->json('has_changes'));
        $this->assertGreaterThan(0, $repeat->json('counts.unchanged'));
    }

    /**
     * Setiap schedule ACTIVE harus direconcile tepat sekali per satu apply,
     * bukan dua kali melalui jalur persist dan jalur agregat.
     */
    public function test_apply_reconciles_each_active_schedule_exactly_once(): void
    {
        $checksheet = $this->seedChecksheetWithSchedule([
            'lifecycle_status' => 'active',
            'is_active' => true,
        ]);
        $schedule = PmSchedule::query()->first();

        // Spy mendelegasikan perilaku nyata ke reconciler asli, sambil mencatat
        // setiap pemanggilan sehingga reconcile ganda dapat dideteksi.
        $real = app(PmScheduleDateReconciler::class);
        $reconcileCalls = [];
        $reconciler = Mockery::mock(PmScheduleDateReconciler::class);
        $reconciler->shouldReceive('preview')
            ->andReturnUsing(fn (PmSchedule $target): array => $real->preview($target));
        $reconciler->shouldReceive('reconcile')
            ->andReturnUsing(function (PmSchedule $target) use ($real, &$reconcileCalls): array {
                $reconcileCalls[] = $target->id;

                return $real->reconcile($target);
            });
        $this->app->instance(PmScheduleDateReconciler::class, $reconciler);

        $payload = $this->wizardPayload([
            'frequency_type' => 'weekly',
            'weekly_days' => [2, 5],
            'monthly_day' => null,
            'operational_from' => '2026-06-15',
            'start_date' => '2026-06-15',
            'generate_until' => '2026-09-15',
        ]);
        $preview = $this->postPreview($checksheet->id, $payload);
        $preview->assertOk();

        $apply = $this->postApply($checksheet->id, $payload, $preview->json('token'));
        $apply->assertOk();
        $apply->assertJsonPath('status', 'applied');

        $this->assertSame(
            [$schedule->id],
            $reconcileCalls,
            'Satu apply hanya boleh mereconcile satu kali per schedule ACTIVE.',
        );
    }
}
