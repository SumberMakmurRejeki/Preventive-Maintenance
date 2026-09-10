<?php

namespace Tests\Feature\Feature\PM;

use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmChecksheetPart;
use App\Models\PmChecksheetStandard;
use App\Models\PmExecution;
use App\Models\PmExecutionHistory;
use App\Models\PmExecutionItem;
use App\Models\PmExecutionMedia;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Models\PrimeNotification;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\PM\PmReviewService;
use DOMDocument;
use DOMElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PmReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected Machine $machine;

    protected PmExecution $execution;

    protected PmExecutionItem $actionItem;

    protected PmExecutionItem $numberItem;

    protected PmScheduleDate $scheduleDate;

    protected PmChecksheetPart $part;

    protected PmChecksheetStandard $actionStandard;

    protected PmChecksheetStandard $numberStandard;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $location = Location::query()->create([
            'location_code' => 'LOC-01',
            'location_name' => 'Line 1',
            'is_active' => true,
        ]);

        $this->machine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'MC-01',
            'machine_name' => 'Filling Machine A',
            'qr_token' => 'qr-mc-01',
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

        $checksheet = PmChecksheet::query()->create([
            'checksheet_code' => 'PM-CH-001',
            'checksheet_name' => 'Checksheet PM',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $checksheetMachine = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
            'assigned_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        $this->part = PmChecksheetPart::query()->create([
            'pm_checksheet_machine_id' => $checksheetMachine->id,
            'part_name' => 'Sistem Mekanikal',
            'is_active' => true,
        ]);

        $this->actionStandard = PmChecksheetStandard::query()->create([
            'pm_checksheet_part_id' => $this->part->id,
            'standard_name' => 'Kondisi Gearbox',
            'input_type' => 'action',
            'action_options' => ['OK', 'LUBRIKASI'],
            'is_required' => true,
            'is_active' => true,
        ]);

        $this->numberStandard = PmChecksheetStandard::query()->create([
            'pm_checksheet_part_id' => $this->part->id,
            'standard_name' => 'Suhu Bearing',
            'input_type' => 'number',
            'target_value' => 50,
            'unit' => 'C',
            'is_required' => true,
            'is_active' => true,
        ]);

        $schedule = PmSchedule::query()->create([
            'pm_checksheet_machine_id' => $checksheetMachine->id,
            'frequency_type' => 'daily',
            'start_date' => now()->subDay()->toDateString(),
            'generate_until' => now()->addDay()->toDateString(),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->scheduleDate = PmScheduleDate::query()->create([
            'pm_schedule_id' => $schedule->id,
            'machine_id' => $this->machine->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'waiting_review',
        ]);

        $this->execution = PmExecution::query()->create([
            'machine_code_snapshot' => 'MC-01',
            'machine_name_snapshot' => 'Filling Machine A',
            'location_code_snapshot' => 'LOC-01',
            'location_name_snapshot' => 'Line 1',
            'pm_schedule_date_id' => $this->scheduleDate->id,
            'machine_id' => $this->machine->id,
            'operator_id' => $this->operator->id,
            'operator_name_snapshot' => $this->operator->name,
            'status' => 'waiting_review',
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subMinutes(20),
        ]);

        $this->actionItem = PmExecutionItem::query()->create([
            'pm_execution_id' => $this->execution->id,
            'pm_checksheet_part_id' => $this->part->id,
            'pm_checksheet_standard_id' => $this->actionStandard->id,
            'part_name_snapshot' => 'Sistem Mekanikal',
            'standard_name_snapshot' => 'Kondisi Gearbox',
            'input_type_snapshot' => 'action',
            'action_options_snapshot' => ['OK', 'LUBRIKASI'],
            'action_value' => 'OK',
            'is_warning' => false,
            'note' => 'Catatan lama',
        ]);

        $this->numberItem = PmExecutionItem::query()->create([
            'pm_execution_id' => $this->execution->id,
            'pm_checksheet_part_id' => $this->part->id,
            'pm_checksheet_standard_id' => $this->numberStandard->id,
            'part_name_snapshot' => 'Sistem Mekanikal',
            'standard_name_snapshot' => 'Suhu Bearing',
            'input_type_snapshot' => 'number',
            'target_value_snapshot' => 50,
            'unit_snapshot' => 'C',
            'number_value' => 50,
            'is_warning' => false,
            'note' => 'Catatan lama',
        ]);

        Storage::disk('public')->put('pm-execution-media/sample.jpg', 'file-content');
        PmExecutionMedia::query()->create([
            'pm_execution_id' => $this->execution->id,
            'pm_checksheet_part_id' => $this->part->id,
            'part_name_snapshot' => 'Sistem Mekanikal',
            'file_type' => 'photo',
            'file_path' => 'pm-execution-media/sample.jpg',
            'file_name' => 'sample.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1000,
            'original_file_size' => 1000,
            'compressed_file_size' => 1000,
            'uploaded_by' => $this->operator->id,
            'uploaded_by_name_snapshot' => $this->operator->name,
        ]);

        PrimeNotification::query()->create([
            'notification_type' => 'pm_waiting_review',
            'title' => 'Waiting review',
            'message' => 'PM waiting review',
            'target_role' => 'admin',
            'related_table' => 'pm_executions',
            'related_id' => $this->execution->id,
            'target_url' => '/pm/review',
        ]);
    }

    public function test_only_admin_can_access_pm_review_pages(): void
    {
        $this->actingAs($this->operator)->get('/pm/review')->assertRedirect('/403');

        $guestSession = GuestSession::query()->create([
            'guest_name' => 'Guest PRIME',
            'session_id' => 'guest-session',
            'login_at' => now(),
        ]);

        $this->withSession([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ])->get('/pm/review')->assertRedirect('/403');
    }

    /**
     * Slice B: identitas transaksi harus tetap memakai snapshot setelah master berubah.
     */
    public function test_pm_review_uses_complete_transaction_snapshots_after_machine_and_location_change(): void
    {
        $this->execution->forceFill([
            'machine_code_snapshot' => 'MC-01-HIST',
            'machine_name_snapshot' => 'Filling Machine Historical',
            'location_code_snapshot' => 'LOC-01-HIST',
            'location_name_snapshot' => 'Line Historical',
        ])->save();

        $location = $this->machine->location;
        $this->machine->forceFill([
            'machine_code' => 'MC-01-LIVE',
            'machine_name' => 'Filling Machine Live',
        ])->save();
        $location->forceFill([
            'location_code' => 'LOC-01-LIVE',
            'location_name' => 'Line Live',
        ])->save();
        $location->delete();

        // Identitas historis dibaca dari bundle snapshot, bukan master yang berubah.
        $this->actingAs($this->admin)
            ->get('/pm/review')
            ->assertOk()
            ->assertSee('MC-01-HIST')
            ->assertSee('Filling Machine Historical')
            ->assertSee('Line Historical')
            ->assertDontSee('MC-01-LIVE')
            ->assertDontSee('Filling Machine Live')
            ->assertDontSee('Line Live');

        $this->actingAs($this->admin)
            ->get("/pm/review/{$this->execution->id}")
            ->assertOk()
            ->assertSee('MC-01-HIST')
            ->assertSee('Filling Machine Historical')
            ->assertSee('Line Historical')
            ->assertDontSee('MC-01-LIVE')
            ->assertDontSee('Filling Machine Live')
            ->assertDontSee('Line Live');
    }

    /**
     * Slice B (penguatan bukti): partial/blank snapshot bundle harus dirender
     * sebagai label legacy secara ATOMIK pada baris eksekusi yang sama —
     * baik di tabel desktop maupun kartu mobile — bukan sekadar label
     * yang muncul di suatu tempat pada halaman.
     *
     * @param  array{machine_code_snapshot:?string,machine_name_snapshot:?string,location_code_snapshot:?string,location_name_snapshot:?string}  $bundle
     */
    #[DataProvider('pmReviewPartialSnapshotBundleProvider')]
    public function test_pm_review_renders_atomic_legacy_identity_on_affected_row_for_partial_snapshots(array $bundle, string $caseLabel): void
    {
        // CAUSALITY: bundle eksekusi target inilah yang dimutasi, dan baris
        // yang diassert di bawah adalah baris untuk eksekusi yang sama.
        $this->execution->forceFill($bundle)->save();

        $response = $this->actingAs($this->admin)->get('/pm/review');
        $response->assertOk();

        $html = $response->getContent();
        $legacy = 'Data historis tidak tersedia (legacy)';

        // Desktop: baris tabel untuk eksekusi yang dimutasi.
        $desktopRow = $this->pmReviewDesktopRow($html, $this->execution->id);
        $this->assertNotNull(
            $desktopRow,
            "[{$caseLabel}] Baris desktop untuk eksekusi #{$this->execution->id} tidak ditemukan.",
        );
        $this->assertStringContainsString($legacy, $desktopRow->textContent ?? '', "[{$caseLabel}] Desktop Machine Code harus legacy.");

        // Tiga sel identitas (kode mesin, nama mesin, lokasi) pada baris desktop
        // harus legacy BERSAMA-SAMA; tidak boleh ada sel identitas yang memakai
        // nilai master live ataupun nilai snapshot non-atomik.
        $desktopIdentityCells = $this->pmReviewDesktopIdentityCells($desktopRow);
        $this->assertCount(3, $desktopIdentityCells, "[{$caseLabel}] Baris desktop harus punya tepat 3 sel identitas (kode/nama mesin + lokasi).");
        foreach ($desktopIdentityCells as $cell) {
            $this->assertSame(
                $legacy,
                trim((string) $cell->textContent),
                "[{$caseLabel}] Sel identitas desktop harus memakai label legacy secara atomik.",
            );
        }

        // Mobile: kartu untuk eksekusi yang sama juga harus legacy atomik.
        $mobileCard = $this->pmReviewMobileCard($html, $this->execution->id);
        $this->assertNotNull(
            $mobileCard,
            "[{$caseLabel}] Kartu mobile untuk eksekusi #{$this->execution->id} tidak ditemukan.",
        );
        $this->assertSame(
            $legacy,
            trim((string) $this->pmReviewMobileCardCodeNode($mobileCard)?->textContent),
            "[{$caseLabel}] Kartu mobile Kode Mesin harus legacy.",
        );
        $this->assertSame(
            $legacy,
            trim((string) $this->pmReviewMobileCardNameNode($mobileCard)?->textContent),
            "[{$caseLabel}] Kartu mobile Nama Mesin harus legacy.",
        );
        $this->assertStringEndsWith(
            $legacy,
            trim((string) $this->pmReviewMobileCardLocationNode($mobileCard)?->textContent),
            "[{$caseLabel}] Kartu mobile Lokasi harus legacy.",
        );

        // VISIBLE-TEXT vs DATA-ATTRIBUTE: nilai master live sengaja tetap ada
        // pada atribut data-* untuk filtering. Buktikan bahwa live master TIDAK
        // muncul sebagai teks identitas historis — scoped ke baris desktop dan
        // kartu mobile — tanpa assertDontSee global yang akan gagal karena data-*.
        foreach (['MC-01', 'Filling Machine A', 'Line 1'] as $liveMasterValue) {
            $this->assertStringNotContainsString(
                $liveMasterValue,
                $this->pmReviewRowVisibleText($desktopRow),
                "[{$caseLabel}] Teks tampilan desktop tidak boleh memuat master live '{$liveMasterValue}'.",
            );
            $this->assertStringNotContainsString(
                $liveMasterValue,
                $this->pmReviewMobileCardVisibleText($mobileCard),
                "[{$caseLabel}] Teks tampilan mobile tidak boleh memuat master live '{$liveMasterValue}'.",
            );
        }
    }

    /**
     * Slice B (penguatan bukti): detail page harus memperlakukan empty-string
     * dan whitespace-only snapshot sebagai bundle legacy pada blok Identitas Mesin.
     */
    #[DataProvider('pmReviewBlankSnapshotBundleProvider')]
    public function test_pm_review_detail_renders_legacy_identity_block_for_blank_snapshots(string $machineCodeSnapshot, string $machineNameSnapshot, string $locationCodeSnapshot, string $locationNameSnapshot, string $caseLabel): void
    {
        // CAUSALITY: bundle eksekusi target inilah yang dimutasi; detail page
        // yang di-GET adalah detail eksekusi yang sama.
        $this->execution->forceFill([
            'machine_code_snapshot' => $machineCodeSnapshot,
            'machine_name_snapshot' => $machineNameSnapshot,
            'location_code_snapshot' => $locationCodeSnapshot,
            'location_name_snapshot' => $locationNameSnapshot,
        ])->save();

        $response = $this->actingAs($this->admin)->get("/pm/review/{$this->execution->id}");
        $response->assertOk();

        // Detail merender satu blok Identitas Mesin atomik: jika bundle tidak
        // usable, ketiga dd harus legacy BERSAMA-SAMA dan tidak boleh ada
        // snapshot parsial yang bocor ke tampilan.
        $identityCard = $this->pmReviewDetailIdentityCard($response->getContent());
        $this->assertNotNull($identityCard, "[{$caseLabel}] Blok Identitas Mesin tidak ditemukan pada detail page.");

        $legacyCount = substr_count($identityCard->ownerDocument->saveHTML($identityCard), 'Data historis tidak tersedia (legacy)');
        $this->assertSame(
            3,
            $legacyCount,
            "[{$caseLabel}] Ketiga identitas (Kode Mesin, Nama Mesin, Lokasi) pada blok Identitas Mesin harus legacy atomik.",
        );

        $visibleText = $this->pmReviewElementVisibleText($identityCard);
        $this->assertStringNotContainsString('MC-01', $visibleText, "[{$caseLabel}] Tampilan identitas tidak boleh fallback ke master live (kode mesin).");
        $this->assertStringNotContainsString('Filling Machine A', $visibleText, "[{$caseLabel}] Tampilan identitas tidak boleh fallback ke master live (nama mesin).");
        $this->assertStringNotContainsString('Line 1', $visibleText, "[{$caseLabel}] Tampilan identitas tidak boleh fallback ke master live (lokasi).");
    }

    /**
     * @return list<array{array{machine_code_snapshot:?string,machine_name_snapshot:?string,location_code_snapshot:?string,location_name_snapshot:?string},string}>
     */
    public static function pmReviewPartialSnapshotBundleProvider(): array
    {
        $completeBundle = [
            'machine_code_snapshot' => 'MC-01',
            'machine_name_snapshot' => 'Filling Machine A',
            'location_code_snapshot' => 'LOC-01',
            'location_name_snapshot' => 'Line 1',
        ];

        $with = static fn (array $overrides): array => array_merge($completeBundle, $overrides);

        return [
            'missing machine_code_snapshot' => [$with(['machine_code_snapshot' => null]), 'missing machine_code_snapshot (null)'],
            'missing machine_name_snapshot' => [$with(['machine_name_snapshot' => null]), 'missing machine_name_snapshot (null)'],
            'missing location_code_snapshot' => [$with(['location_code_snapshot' => null]), 'missing location_code_snapshot (null)'],
            'missing location_name_snapshot' => [$with(['location_name_snapshot' => null]), 'missing location_name_snapshot (null)'],
            '2 of 4 usable' => [$with(['machine_code_snapshot' => null, 'location_name_snapshot' => null]), '2/4 usable'],
            '1 of 4 usable' => [$with(['machine_code_snapshot' => null, 'machine_name_snapshot' => null, 'location_code_snapshot' => null]), '1/4 usable'],
            '0 of 4 usable' => [[
                'machine_code_snapshot' => null,
                'machine_name_snapshot' => null,
                'location_code_snapshot' => null,
                'location_name_snapshot' => null,
            ], '0/4 usable'],
            'empty string snapshot' => [$with([
                'machine_code_snapshot' => '',
                'machine_name_snapshot' => '',
                'location_code_snapshot' => '',
                'location_name_snapshot' => '',
            ]), 'empty string (all four fields)'],
            'whitespace-only snapshot' => [$with([
                'machine_code_snapshot' => '   ',
                'machine_name_snapshot' => '   ',
                'location_code_snapshot' => '   ',
                'location_name_snapshot' => '   ',
            ]), 'whitespace-only (all four fields)'],
        ];
    }

    /**
     * @return list<array{string,string,string,string,string}>
     */
    public static function pmReviewBlankSnapshotBundleProvider(): array
    {
        return [
            'empty string snapshot fields' => ['', '', '', '', 'empty string'],
            'whitespace-only snapshot fields' => ['   ', " \t ", " \t", '  ', 'whitespace-only'],
        ];
    }

    private function pmReviewDesktopRow(string $html, int $executionId): ?DOMElement
    {
        $rows = $this->pmReviewDom($html)->getElementsByTagName('tr');

        foreach ($rows as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            foreach ($row->getElementsByTagName('a') as $link) {
                if (parse_url($link->getAttribute('href'), PHP_URL_PATH) === "/pm/review/{$executionId}") {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * @return list<DOMElement>
     */
    private function pmReviewDesktopIdentityCells(DOMElement $row): array
    {
        $cells = $row->getElementsByTagName('td');
        $identityCells = [];
        $index = 0;

        foreach ($cells as $cell) {
            if ($index >= 2 && $index <= 4 && $cell instanceof DOMElement) {
                $identityCells[] = $cell;
            }
            $index++;
        }

        return $identityCells;
    }

    private function pmReviewMobileCard(string $html, int $executionId): ?DOMElement
    {
        $articles = $this->pmReviewDom($html)->getElementsByTagName('article');

        foreach ($articles as $article) {
            if (! $article instanceof DOMElement) {
                continue;
            }

            foreach ($article->getElementsByTagName('a') as $link) {
                if (parse_url($link->getAttribute('href'), PHP_URL_PATH) === "/pm/review/{$executionId}") {
                    return $article;
                }
            }
        }

        return null;
    }

    private function pmReviewMobileCardCodeNode(DOMElement $card): ?DOMElement
    {
        foreach ($card->getElementsByTagName('p') as $node) {
            if ($node instanceof DOMElement && $node->getAttribute('class') === 'text-[12px] font-semibold text-[#615d59]') {
                return $node;
            }
        }

        return null;
    }

    private function pmReviewMobileCardNameNode(DOMElement $card): ?DOMElement
    {
        foreach ($card->getElementsByTagName('h3') as $node) {
            if ($node instanceof DOMElement) {
                return $node;
            }
        }

        return null;
    }

    private function pmReviewMobileCardLocationNode(DOMElement $card): ?DOMElement
    {
        foreach ($card->getElementsByTagName('p') as $node) {
            if ($node instanceof DOMElement && str_starts_with((string) $node->textContent, 'Lokasi:')) {
                return $node;
            }
        }

        return null;
    }

    private function pmReviewDetailIdentityCard(string $html): ?DOMElement
    {
        $sections = $this->pmReviewDom($html)->getElementsByTagName('section');

        foreach ($sections as $section) {
            if (! $section instanceof DOMElement) {
                continue;
            }

            foreach ($section->getElementsByTagName('h3') as $heading) {
                if ($heading instanceof DOMElement && trim((string) $heading->textContent) === 'Identitas Mesin') {
                    return $section;
                }
            }
        }

        return null;
    }

    private function pmReviewDom(string $html): DOMDocument
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return $document;
    }

    private function pmReviewRowVisibleText(DOMElement $row): string
    {
        return trim((string) $row->textContent);
    }

    private function pmReviewMobileCardVisibleText(DOMElement $card): string
    {
        return trim((string) $card->textContent);
    }

    private function pmReviewElementVisibleText(DOMElement $element): string
    {
        return trim((string) $element->textContent);
    }

    public function test_admin_can_view_pm_review_list_and_detail(): void
    {
        $this->actingAs($this->admin)
            ->get('/pm/review')
            ->assertOk()
            ->assertSee('PM Review')
            ->assertSee('MC-01');

        $this->actingAs($this->admin)
            ->get("/pm/review/{$this->execution->id}")
            ->assertOk()
            ->assertSee('Kondisi Gearbox')
            ->assertSee('Suhu Bearing');
    }

    public function test_admin_can_update_pm_review_and_history_is_created(): void
    {
        $updatedSubmittedAt = now()->subMinutes(10)->seconds(0);

        $response = $this->actingAs($this->admin)->put("/pm/review/{$this->execution->id}", [
            'items' => [
                $this->actionItem->id => ['action_value' => 'LUBRIKASI'],
                $this->numberItem->id => ['number_value' => 45],
            ],
            'part_notes' => [
                $this->part->id => 'Catatan baru',
            ],
            'submitted_at' => $updatedSubmittedAt->format('Y-m-d\TH:i'),
            'change_note' => 'Koreksi input operator',
            'review_note' => 'Perlu dipantau minggu depan',
        ]);

        $response->assertRedirect("/pm/review/{$this->execution->id}");

        $this->assertDatabaseHas('pm_execution_items', [
            'id' => $this->actionItem->id,
            'action_value' => 'LUBRIKASI',
        ]);

        $this->assertDatabaseHas('pm_execution_items', [
            'id' => $this->numberItem->id,
            'number_value' => 45,
            'is_warning' => 1,
        ]);

        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'submitted_at' => $updatedSubmittedAt->format('Y-m-d H:i:00'),
        ]);

        $this->assertDatabaseHas('pm_execution_history', [
            'pm_execution_id' => $this->execution->id,
            'change_note' => 'Koreksi input operator',
        ]);

        $this->assertDatabaseHas('pm_execution_history', [
            'pm_execution_id' => $this->execution->id,
            'field_name' => 'Tanggal Submit PM',
            'new_value' => $updatedSubmittedAt->format('Y-m-d H:i:00'),
        ]);
    }

    /**
     * RI-012 R-SQLITE-1: model waiting_review yang stale tidak boleh
     * memutasi execution yang sudah berubah menjadi approved di database.
     */
    public function test_stale_waiting_review_model_cannot_update_approved_execution(): void
    {
        $staleExecution = PmExecution::query()->findOrFail($this->execution->id);
        $initialExecution = $this->execution->fresh();
        $initialActionItem = $this->actionItem->fresh();
        $initialNumberItem = $this->numberItem->fresh();
        $initialHistoryCount = PmExecutionHistory::query()
            ->where('pm_execution_id', $this->execution->id)
            ->count();
        $initialActivityCount = UserActivityLog::query()
            ->where('module_name', 'pm_review')
            ->where('action', 'update')
            ->where('record_id', $this->execution->id)
            ->count();

        // Pisahkan perubahan otoritatif di database dari model yang tetap stale
        // untuk membuktikan service tidak memakai status dari memory sebagai safety check.
        PmExecution::query()->whereKey($this->execution->id)->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'approved_by_name_snapshot' => $this->admin->name,
        ]);

        try {
            app(PmReviewService::class)->update(
                request: $this->s1Request(),
                execution: $staleExecution,
                admin: $this->admin,
                payload: [
                    'items' => [
                        $this->actionItem->id => ['action_value' => 'LUBRIKASI'],
                        $this->numberItem->id => ['number_value' => 45],
                    ],
                    'part_notes' => [
                        $this->part->id => 'Catatan stale ditolak',
                    ],
                    'submitted_at' => now()->addHour()->format('Y-m-d\TH:i'),
                    'change_note' => 'Update stale seharusnya ditolak',
                    'review_note' => 'Review note stale',
                ],
            );
            $this->fail('Update harus ditolak saat execution sudah approved di database.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'approved',
                mb_strtolower(collect($exception->errors())->flatten()->first()),
            );
        }

        // Semua state transaksi dan side effect update harus tetap persis seperti
        // sebelum request stale dijalankan, selain metadata approval yang memang
        // diubah oleh simulasi authoritative update di atas.
        $this->assertSame('approved', $this->execution->fresh()->status);
        $this->assertSame($initialExecution->machine_code_snapshot, $this->execution->fresh()->machine_code_snapshot);
        $this->assertSame($initialExecution->machine_name_snapshot, $this->execution->fresh()->machine_name_snapshot);
        $this->assertSame($initialExecution->location_code_snapshot, $this->execution->fresh()->location_code_snapshot);
        $this->assertSame($initialExecution->location_name_snapshot, $this->execution->fresh()->location_name_snapshot);
        $this->assertSame(
            $initialExecution->submitted_at?->toDateTimeString(),
            $this->execution->fresh()->submitted_at?->toDateTimeString(),
        );
        $this->assertSame($initialExecution->review_note, $this->execution->fresh()->review_note);
        $this->assertSame($initialActionItem->action_value, $this->actionItem->fresh()->action_value);
        $this->assertSame($initialNumberItem->number_value, $this->numberItem->fresh()->number_value);
        $this->assertSame($initialNumberItem->is_warning, $this->numberItem->fresh()->is_warning);
        $this->assertSame($initialNumberItem->warning_message, $this->numberItem->fresh()->warning_message);
        $this->assertSame($initialActionItem->note, $this->actionItem->fresh()->note);
        $this->assertSame($initialNumberItem->note, $this->numberItem->fresh()->note);
        $this->assertSame($initialHistoryCount, PmExecutionHistory::query()
            ->where('pm_execution_id', $this->execution->id)
            ->count());
        $this->assertSame($initialActivityCount, UserActivityLog::query()
            ->where('module_name', 'pm_review')
            ->where('action', 'update')
            ->where('record_id', $this->execution->id)
            ->count());
    }

    /**
     * RI-012 R-SQLITE-2: request update langsung pada execution approved harus
     * ditolak tanpa mengubah field transaksi atau menambah evidence update.
     */
    public function test_approved_execution_update_is_rejected_without_mutation(): void
    {
        $this->execution->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'approved_by_name_snapshot' => $this->admin->name,
        ]);
        $this->scheduleDate->update(['status' => 'approved']);

        $initialExecution = $this->execution->fresh();
        $initialActionItem = $this->actionItem->fresh();
        $initialNumberItem = $this->numberItem->fresh();
        $initialHistoryCount = PmExecutionHistory::query()
            ->where('pm_execution_id', $this->execution->id)
            ->count();
        $initialActivityCount = UserActivityLog::query()
            ->where('module_name', 'pm_review')
            ->where('action', 'update')
            ->where('record_id', $this->execution->id)
            ->count();

        // Jalur HTTP memakai payload valid agar rejection berasal dari lifecycle
        // approved, bukan dari validasi field atau authorization.
        $response = $this->from("/pm/review/{$this->execution->id}")
            ->actingAs($this->admin)
            ->put("/pm/review/{$this->execution->id}", [
                'items' => [
                    $this->actionItem->id => ['action_value' => 'LUBRIKASI'],
                    $this->numberItem->id => ['number_value' => 45],
                ],
                'part_notes' => [
                    $this->part->id => 'Catatan approved ditolak',
                ],
                'submitted_at' => now()->addHour()->format('Y-m-d\TH:i'),
                'change_note' => 'Update approved seharusnya ditolak',
                'review_note' => 'Review note approved',
            ]);

        $response->assertRedirect("/pm/review/{$this->execution->id}");
        $response->assertSessionHasErrors('execution');

        // Rejection tidak boleh mengubah execution, item, history, atau activity log.
        $this->assertSame($initialExecution->machine_code_snapshot, $this->execution->fresh()->machine_code_snapshot);
        $this->assertSame($initialExecution->machine_name_snapshot, $this->execution->fresh()->machine_name_snapshot);
        $this->assertSame($initialExecution->location_code_snapshot, $this->execution->fresh()->location_code_snapshot);
        $this->assertSame($initialExecution->location_name_snapshot, $this->execution->fresh()->location_name_snapshot);
        $this->assertSame('approved', $this->execution->fresh()->status);
        $this->assertSame($initialExecution->submitted_at?->toDateTimeString(), $this->execution->fresh()->submitted_at?->toDateTimeString());
        $this->assertSame($initialExecution->review_note, $this->execution->fresh()->review_note);
        $this->assertSame($initialActionItem->action_value, $this->actionItem->fresh()->action_value);
        $this->assertSame($initialNumberItem->number_value, $this->numberItem->fresh()->number_value);
        $this->assertSame($initialNumberItem->is_warning, $this->numberItem->fresh()->is_warning);
        $this->assertSame($initialNumberItem->warning_message, $this->numberItem->fresh()->warning_message);
        $this->assertSame($initialActionItem->note, $this->actionItem->fresh()->note);
        $this->assertSame($initialNumberItem->note, $this->numberItem->fresh()->note);
        $this->assertSame($initialHistoryCount, PmExecutionHistory::query()
            ->where('pm_execution_id', $this->execution->id)
            ->count());
        $this->assertSame($initialActivityCount, UserActivityLog::query()
            ->where('module_name', 'pm_review')
            ->where('action', 'update')
            ->where('record_id', $this->execution->id)
            ->count());
    }

    public function test_machine_landing_uses_updated_submitted_at_after_pm_review_edit(): void
    {
        $updatedSubmittedAt = now()->subMinutes(5)->seconds(0);

        $this->actingAs($this->admin)->put("/pm/review/{$this->execution->id}", [
            'items' => [
                $this->actionItem->id => ['action_value' => 'OK'],
                $this->numberItem->id => ['number_value' => 50],
            ],
            'part_notes' => [
                $this->part->id => 'Catatan lama',
            ],
            'submitted_at' => $updatedSubmittedAt->format('Y-m-d\TH:i'),
            'change_note' => 'Koreksi tanggal submit PM',
            'review_note' => 'Tetap menunggu review',
        ])->assertRedirect("/pm/review/{$this->execution->id}");

        $this->actingAs($this->operator)
            ->get("/machines/{$this->machine->machine_code}")
            ->assertOk()
            ->assertSee($updatedSubmittedAt->translatedFormat('d M Y'));
    }

    public function test_admin_can_approve_pm_review(): void
    {
        $response = $this->actingAs($this->admin)->patch("/pm/review/{$this->execution->id}/approve", [
            'review_note' => 'Approve by admin',
        ]);

        $response->assertRedirect("/pm/review/{$this->execution->id}");

        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'related_table' => 'pm_executions',
            'related_id' => $this->execution->id,
        ]);
    }

    public function test_admin_cannot_delete_protected_execution_and_receives_business_message(): void
    {
        // Stale test updated: previous expectation was destructive delete.
        // Per ADR-003, protected executions (in_progress, waiting_review, approved) must be rejected.
        // setUp creates waiting_review execution, which is protected.
        $response = $this->actingAs($this->admin)->delete("/pm/review/{$this->execution->id}");

        // Rejection redirects back to detail with business-facing error
        $response->assertRedirect("/pm/review/{$this->execution->id}");
        $response->assertSessionHas('flash_error', 'Transaksi PM yang sudah dimulai tidak dapat dihapus karena merupakan data pekerjaan/histori.');

        // Execution, items, media, and schedule date all preserved
        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'waiting_review',
        ]);

        $this->assertDatabaseHas('pm_execution_items', [
            'pm_execution_id' => $this->execution->id,
        ]);

        $this->assertDatabaseHas('pm_execution_media', [
            'pm_execution_id' => $this->execution->id,
        ]);

        Storage::disk('public')->assertExists('pm-execution-media/sample.jpg');

        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'status' => 'waiting_review',
        ]);
    }

    public function test_admin_cannot_delete_in_progress_execution(): void
    {
        // Scenario A: in_progress execution delete protection
        $this->execution->update(['status' => 'in_progress']);
        $this->scheduleDate->update(['status' => 'in_progress']);

        // Create execution history to verify preservation
        $history = PmExecutionHistory::query()->create([
            'pm_execution_id' => $this->execution->id,
            'changed_by' => $this->operator->id,
            'changed_by_name_snapshot' => $this->operator->name,
            'field_name' => 'status',
            'old_value' => 'draft',
            'new_value' => 'in_progress',
            'change_note' => 'PM dimulai',
        ]);

        $initialItemCount = PmExecutionItem::query()->where('pm_execution_id', $this->execution->id)->count();
        $initialMediaCount = PmExecutionMedia::query()->where('pm_execution_id', $this->execution->id)->count();
        $initialHistoryCount = PmExecutionHistory::query()->where('pm_execution_id', $this->execution->id)->count();

        $response = $this->actingAs($this->admin)->delete("/pm/review/{$this->execution->id}");

        // Rejection with business-facing message
        $response->assertRedirect("/pm/review/{$this->execution->id}");
        $response->assertSessionHas('flash_error');

        // Zero mutation: execution still exists
        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'in_progress',
        ]);

        // Schedule date status preserved
        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'status' => 'in_progress',
        ]);

        // Items preserved
        $this->assertEquals($initialItemCount, PmExecutionItem::query()->where('pm_execution_id', $this->execution->id)->count());

        // Media preserved (both DB rows and files)
        $this->assertEquals($initialMediaCount, PmExecutionMedia::query()->where('pm_execution_id', $this->execution->id)->count());
        Storage::disk('public')->assertExists('pm-execution-media/sample.jpg');

        // History preserved
        $this->assertEquals($initialHistoryCount, PmExecutionHistory::query()->where('pm_execution_id', $this->execution->id)->count());
        $this->assertDatabaseHas('pm_execution_history', ['id' => $history->id]);
    }

    public function test_admin_cannot_delete_waiting_review_execution(): void
    {
        // Scenario B: waiting_review execution delete protection
        // setUp already creates waiting_review execution
        $initialItemCount = PmExecutionItem::query()->where('pm_execution_id', $this->execution->id)->count();
        $initialMediaCount = PmExecutionMedia::query()->where('pm_execution_id', $this->execution->id)->count();

        $response = $this->actingAs($this->admin)->delete("/pm/review/{$this->execution->id}");

        // Rejection with business-facing message
        $response->assertRedirect("/pm/review/{$this->execution->id}");
        $response->assertSessionHas('flash_error');

        // Zero mutation: execution still exists
        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'waiting_review',
        ]);

        // Schedule date status preserved
        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'status' => 'waiting_review',
        ]);

        // Items preserved
        $this->assertEquals($initialItemCount, PmExecutionItem::query()->where('pm_execution_id', $this->execution->id)->count());

        // Media preserved
        $this->assertEquals($initialMediaCount, PmExecutionMedia::query()->where('pm_execution_id', $this->execution->id)->count());
        Storage::disk('public')->assertExists('pm-execution-media/sample.jpg');
    }

    public function test_admin_cannot_delete_approved_execution(): void
    {
        // Scenario C: approved execution delete protection
        $this->execution->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'approved_by_name_snapshot' => $this->admin->name,
        ]);
        $this->scheduleDate->update(['status' => 'approved']);

        $initialItemCount = PmExecutionItem::query()->where('pm_execution_id', $this->execution->id)->count();
        $initialMediaCount = PmExecutionMedia::query()->where('pm_execution_id', $this->execution->id)->count();

        $response = $this->actingAs($this->admin)->delete("/pm/review/{$this->execution->id}");

        // Rejection with business-facing message
        $response->assertRedirect("/pm/review/{$this->execution->id}");
        $response->assertSessionHas('flash_error');

        // Zero mutation: execution still exists with approved metadata
        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
        ]);

        // Schedule date status preserved
        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'status' => 'approved',
        ]);

        // Items preserved
        $this->assertEquals($initialItemCount, PmExecutionItem::query()->where('pm_execution_id', $this->execution->id)->count());

        // Media preserved
        $this->assertEquals($initialMediaCount, PmExecutionMedia::query()->where('pm_execution_id', $this->execution->id)->count());
        Storage::disk('public')->assertExists('pm-execution-media/sample.jpg');
    }

    public function test_non_admin_cannot_delete_pm_execution(): void
    {
        // Scenario D: authorization regression
        $response = $this->actingAs($this->operator)->delete("/pm/review/{$this->execution->id}");

        // Authorization enforced
        $response->assertRedirect('/403');

        // Execution unchanged
        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'waiting_review',
        ]);
    }

    public function test_admin_can_still_view_and_approve_pm_review(): void
    {
        // Scenario E: valid PM Review workflow regression
        // View detail
        $this->actingAs($this->admin)
            ->get("/pm/review/{$this->execution->id}")
            ->assertOk()
            ->assertSee('Kondisi Gearbox');

        // Edit workflow still works
        $this->actingAs($this->admin)
            ->put("/pm/review/{$this->execution->id}", [
                'items' => [
                    $this->actionItem->id => ['action_value' => 'LUBRIKASI'],
                    $this->numberItem->id => ['number_value' => 55],
                ],
                'submitted_at' => now()->subMinutes(10)->format('Y-m-d\TH:i'),
                'change_note' => 'Koreksi review',
                'review_note' => 'Perlu dipantau',
            ])
            ->assertRedirect("/pm/review/{$this->execution->id}");

        $this->assertDatabaseHas('pm_execution_items', [
            'id' => $this->actionItem->id,
            'action_value' => 'LUBRIKASI',
        ]);

        // Approve workflow still works
        $this->actingAs($this->admin)
            ->patch("/pm/review/{$this->execution->id}/approve", [
                'review_note' => 'Approved',
            ])
            ->assertRedirect("/pm/review/{$this->execution->id}");

        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'approved',
        ]);
    }

    // ====================================================================
    // ADR-004 Slice 1 — Canonical Serialization + Lifecycle Contract
    // ====================================================================

    /**
     * S1-R1: Approval mentransisikan execution+occurrence ke approved dengan benar.
     * Regression test — harus tetap lulus setelah lock reorder.
     */
    public function test_s1_approval_transitions_execution_and_occurrence_to_approved(): void
    {
        $this->actingAs($this->admin)
            ->patch("/pm/review/{$this->execution->id}/approve", [
                'review_note' => 'Disetujui setelah canonical resolver.',
            ])
            ->assertRedirect("/pm/review/{$this->execution->id}");

        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'review_note' => 'Disetujui setelah canonical resolver.',
        ]);
        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'status' => 'approved',
        ]);
    }

    /**
     * S1-R2: Approval membaca ulang lifecycle di bawah Machine → Date → Execution locks.
     * Dibuktikan: jika status execution berubah jadi approved di DB sebelum approve dipanggil,
     * approve menolak dengan pesan yang tepat.
     */
    public function test_s1_approval_rereads_lifecycle_under_locks(): void
    {
        // Ubah status di DB menjadi approved sebelum approve dipanggil
        PmExecution::query()->whereKey($this->execution->id)->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'approved_by_name_snapshot' => $this->admin->name,
        ]);

        try {
            app(PmReviewService::class)->approve(
                request: $this->s1Request(),
                execution: $this->execution, // stale model, status masih waiting_review di memory
                admin: $this->admin,
                reviewNote: 'Double approve attempt',
            );
            $this->fail('Approve harus ditolak saat execution sudah approved.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(
                'approved',
                mb_strtolower(collect($e->errors())->flatten()->first()),
            );
        }
    }

    /**
     * S1-R3: Test-test ADR-003 protected delete tetap lulus setelah perubahan Slice 1.
     * Ini adalah regression meta-test — memanggil skenario yang sama.
     */
    public function test_s1_adr003_protected_delete_tests_still_pass(): void
    {
        // Delete pada execution waiting_review (setUp default) tetap ditolak
        $response = $this->actingAs($this->admin)->delete("/pm/review/{$this->execution->id}");
        $response->assertRedirect("/pm/review/{$this->execution->id}");
        $response->assertSessionHas('flash_error');

        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'status' => 'waiting_review',
        ]);
    }

    /**
     * S1-R4: Execution dengan machine mismatch tidak boleh di-approve.
     * Parent chain harus valid sebelum status execution dimutasi.
     */
    public function test_s1_approval_rejects_execution_machine_mismatch_without_mutation(): void
    {
        $otherMachine = Machine::query()->create([
            'location_id' => $this->machine->location_id,
            'machine_code' => 'MC-02',
            'machine_name' => 'Filling Machine B',
            'qr_token' => 'qr-mc-02',
            'is_active' => true,
        ]);

        // Simulasikan data historis rusak: occurrence tetap milik machine asli,
        // tetapi execution menunjuk machine lain.
        PmExecution::query()->whereKey($this->execution->id)->update([
            'machine_id' => $otherMachine->id,
            'status' => 'waiting_review',
        ]);

        try {
            app(PmReviewService::class)->approve(
                request: $this->s1Request(),
                execution: $this->execution,
                admin: $this->admin,
                reviewNote: 'Mismatch harus ditolak',
            );
            $this->fail('Approve harus ditolak saat machine execution mismatch dengan occurrence.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(
                'tidak sesuai',
                mb_strtolower(collect($e->errors())->flatten()->first()),
            );
        }

        $this->assertDatabaseHas('pm_executions', [
            'id' => $this->execution->id,
            'machine_id' => $otherMachine->id,
            'status' => 'waiting_review',
        ]);
        $this->assertDatabaseHas('pm_schedule_dates', [
            'id' => $this->scheduleDate->id,
            'machine_id' => $this->machine->id,
            'status' => 'waiting_review',
        ]);
    }

    private function s1Request(): Request
    {
        $request = Request::create('/', 'GET');
        $request->setLaravelSession(app('session.store'));

        return $request;
    }
}
