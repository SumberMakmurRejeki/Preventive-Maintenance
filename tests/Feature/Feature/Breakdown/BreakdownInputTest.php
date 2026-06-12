<?php

namespace Tests\Feature\Feature\Breakdown;

use App\Models\Breakdown;
use App\Models\BreakdownMedia;
use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmChecksheetPart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BreakdownInputTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected Machine $machine;

    protected PmChecksheetPart $part;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $location = Location::query()->create([
            'location_code' => 'LOC-01',
            'location_name' => 'Line 1, Area B',
            'is_active' => true,
        ]);

        $this->machine = Machine::query()->create([
            'location_id' => $location->id,
            'machine_code' => 'MCH-001',
            'machine_name' => 'Mesin CNC Milling',
            'qr_token' => 'qr-mch-001',
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
            'checksheet_name' => 'Checksheet MCH-001',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $checksheetMachine = PmChecksheetMachine::query()->create([
            'pm_checksheet_id' => $checksheet->id,
            'machine_id' => $this->machine->id,
            'created_by' => $this->admin->id,
        ]);

        $this->part = PmChecksheetPart::query()->create([
            'pm_checksheet_machine_id' => $checksheetMachine->id,
            'part_name' => 'Gearbox',
            'is_active' => true,
        ]);
    }

    public function test_admin_and_operator_can_open_breakdown_input_page(): void
    {
        $this->actingAs($this->admin)
            ->get("/breakdown/input/{$this->machine->machine_code}")
            ->assertOk()
            ->assertSee('Input Breakdown')
            ->assertSee('Part Mesin Terganggu');

        $this->actingAs($this->operator)
            ->get("/breakdown/input/{$this->machine->machine_code}")
            ->assertOk()
            ->assertSee('Submit Breakdown');
    }

    public function test_admin_and_operator_can_open_breakdown_machine_picker_from_sidebar_route(): void
    {
        $this->actingAs($this->admin)
            ->get('/breakdown/input')
            ->assertOk()
            ->assertSee('Input Breakdown')
            ->assertSee('Mesin CNC Milling');

        $this->actingAs($this->operator)
            ->get('/breakdown/input')
            ->assertOk()
            ->assertSee('MCH-001');
    }

    public function test_guest_cannot_open_breakdown_input_page(): void
    {
        $guestSession = GuestSession::query()->create([
            'guest_name' => 'Guest Prime',
            'session_id' => 'guest-session',
            'login_at' => now(),
        ]);

        $this->withSession([
            'guest_session_id' => $guestSession->id,
            'guest_name' => $guestSession->guest_name,
        ])->get("/breakdown/input/{$this->machine->machine_code}")
            ->assertRedirect('/403');
    }

    public function test_inactive_machine_cannot_open_breakdown_input_page(): void
    {
        $this->machine->forceFill(['is_active' => false])->save();

        $this->actingAs($this->operator)
            ->get("/breakdown/input/{$this->machine->machine_code}")
            ->assertRedirect("/machines/{$this->machine->machine_code}");
    }

    public function test_store_breakdown_with_existing_part_creates_open_breakdown_and_notification(): void
    {
        $response = $this->actingAs($this->operator)->post("/breakdown/input/{$this->machine->machine_code}", [
            'part_selection' => (string) $this->part->id,
            'problem' => 'Bearing bunyi kasar',
            'breakdown_at' => now()->format('Y-m-d H:i:s'),
            'open_note' => 'Butuh pengecekan teknisi',
        ]);

        $response->assertRedirect("/machines/{$this->machine->machine_code}");

        $breakdown = Breakdown::query()->firstOrFail();
        $this->assertSame('open', $breakdown->status);
        $this->assertSame($this->part->id, $breakdown->pm_checksheet_part_id);
        $this->assertNull($breakdown->custom_part_name);
        $this->assertSame('Gearbox', $breakdown->part_name_snapshot);
        $this->assertSame($this->operator->id, $breakdown->created_by);

        $this->assertDatabaseHas('notifications', [
            'notification_type' => 'breakdown_open',
            'related_table' => 'breakdowns',
            'related_id' => $breakdown->id,
            'target_role' => 'admin',
        ]);
    }

    public function test_store_breakdown_with_other_part_saves_custom_part_name(): void
    {
        $this->actingAs($this->admin)->post("/breakdown/input/{$this->machine->machine_code}", [
            'part_selection' => 'other',
            'custom_part_name' => 'Hydraulic Valve',
            'problem' => 'Valve macet',
            'breakdown_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect("/machines/{$this->machine->machine_code}");

        $this->assertDatabaseHas('breakdowns', [
            'machine_id' => $this->machine->id,
            'pm_checksheet_part_id' => null,
            'custom_part_name' => 'Hydraulic Valve',
            'part_name_snapshot' => 'Hydraulic Valve',
            'status' => 'open',
        ]);
    }

    public function test_problem_and_breakdown_at_are_required(): void
    {
        $this->actingAs($this->operator)->from("/breakdown/input/{$this->machine->machine_code}")
            ->post("/breakdown/input/{$this->machine->machine_code}", [
                'part_selection' => (string) $this->part->id,
                'problem' => '',
                'breakdown_at' => '',
            ])
            ->assertRedirect("/breakdown/input/{$this->machine->machine_code}")
            ->assertSessionHasErrors(['problem', 'breakdown_at']);
    }

    public function test_upload_and_delete_temporary_breakdown_media(): void
    {
        $uploadResponse = $this->actingAs($this->operator)->post('/breakdown/media/upload', [
            'machine_code' => $this->machine->machine_code,
            'media_file' => UploadedFile::fake()->image('kerusakan.jpg', 600, 400),
        ]);

        $uploadResponse->assertOk();
        $mediaId = (string) $uploadResponse->json('media.id');
        $mediaPath = (string) $uploadResponse->json('media.file_path');

        Storage::disk('public')->assertExists($mediaPath);

        $this->actingAs($this->operator)->delete('/breakdown/media/' . $mediaId, [
            'machine_code' => $this->machine->machine_code,
        ])->assertOk();

        Storage::disk('public')->assertMissing($mediaPath);
    }

    public function test_store_breakdown_with_media_saves_breakdown_media_and_shows_on_machine_landing(): void
    {
        $this->actingAs($this->operator)->post("/breakdown/input/{$this->machine->machine_code}", [
            'part_selection' => (string) $this->part->id,
            'problem' => 'Vibrasi tinggi pada spindle',
            'breakdown_at' => now()->format('Y-m-d H:i:s'),
            'media' => [
                UploadedFile::fake()->image('vibrasi.jpg', 500, 500),
            ],
        ])->assertRedirect("/machines/{$this->machine->machine_code}");

        $breakdown = Breakdown::query()->firstOrFail();
        $media = BreakdownMedia::query()->firstOrFail();
        $this->assertSame($breakdown->id, $media->breakdown_id);
        Storage::disk('public')->assertExists($media->file_path);

        $this->actingAs($this->operator)
            ->get("/machines/{$this->machine->machine_code}")
            ->assertOk()
            ->assertSee($breakdown->breakdown_code)
            ->assertSee('Vibrasi tinggi pada spindle');
    }
}
