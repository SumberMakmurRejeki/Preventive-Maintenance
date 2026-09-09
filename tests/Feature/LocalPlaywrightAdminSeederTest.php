<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Machine;
use App\Models\User;
use Database\Seeders\LocalPlaywrightAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Test LocalPlaywrightAdminSeeder — semua safety gate sebelum
 * membuat admin lokal untuk Playwright UAT.
 *
 * Test menggunakan SQLite :memory: sehingga tidak menyentuh
 * database development nyata.
 */
class LocalPlaywrightAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $testUsername = 'playwright.admin';

    private string $testPassword = 'Playwright@2026';

    protected function setUp(): void
    {
        parent::setUp();

        app()->detectEnvironment(static fn (): string => 'local');
        config()->set('prime-local-bootstrap.username', $this->testUsername);
        config()->set('prime-local-bootstrap.password', $this->testPassword);
    }

    /**
     * Helper: buat test double yang mengontrol database identity tanpa koneksi
     * development. Production resolver tetap membaca koneksi User model.
     */
    private function createSeederWithDbIdentity(?string $dbName): LocalPlaywrightAdminSeeder
    {
        return new class($dbName) extends LocalPlaywrightAdminSeeder
        {
            public function __construct(private readonly ?string $dbName) {}

            protected function resolveCurrentDatabaseName(): ?string
            {
                return $this->dbName;
            }
        };
    }

    private function createSeederWithResolverFailure(): LocalPlaywrightAdminSeeder
    {
        return new class extends LocalPlaywrightAdminSeeder
        {
            protected function resolveCurrentDatabaseName(): ?string
            {
                throw new \RuntimeException('Synthetic resolver failure.');
            }
        };
    }

    // ─── 1. NON-LOCAL REFUSAL ────────────────────────────────────────

    public function test_it_refuses_when_environment_is_not_local(): void
    {
        // Setel environment ke production via detectEnvironment (satu-satunya
        // cara yang dipatuhi app()->environment() di dalam satu test process).
        app()->detectEnvironment(static fn (): string => 'production');

        $seeder = $this->createSeederWithDbIdentity('db_preventive_maintenance');

        try {
            $seeder->run();
            $this->fail('Non-local environment must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('local', $exception->getMessage());
        }

        $this->assertSame(0, User::count());
    }

    // ─── 2. MISSING USERNAME ─────────────────────────────────────────

    public function test_it_refuses_when_username_is_blank(): void
    {
        config()->set('prime-local-bootstrap.username', '');

        $seeder = $this->createSeederWithDbIdentity('db_preventive_maintenance');

        try {
            $seeder->run();
            $this->fail('Blank username must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('username', $exception->getMessage());
        }

        $this->assertSame(0, User::count());
    }

    // ─── 3. MISSING PASSWORD ─────────────────────────────────────────

    public function test_it_refuses_when_password_is_blank(): void
    {
        config()->set('prime-local-bootstrap.password', '');

        $seeder = $this->createSeederWithDbIdentity('db_preventive_maintenance');

        try {
            $seeder->run();
            $this->fail('Blank password must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('password', $exception->getMessage());
        }

        $this->assertSame(0, User::count());
    }

    // ─── 3B. WHITESPACE PASSWORD ─────────────────────────────────────

    public function test_it_refuses_when_password_contains_only_whitespace(): void
    {
        config()->set('prime-local-bootstrap.password', '   ');

        $seeder = $this->createSeederWithDbIdentity('db_preventive_maintenance');

        try {
            $seeder->run();
            $this->fail('Whitespace-only password must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('password', $exception->getMessage());
        }

        // Pastikan validasi berhenti sebelum membuat akun apa pun.
        $this->assertSame(0, User::count());
    }

    // ─── 4. DATABASE MISMATCH ────────────────────────────────────────

    public function test_it_refuses_on_database_name_mismatch(): void
    {
        User::factory()->create(['username' => $this->testUsername]);

        $seeder = $this->createSeederWithDbIdentity('wrong_database');

        try {
            $seeder->run();
            $this->fail('A database mismatch must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('database', $exception->getMessage());
        }

        $this->assertSame(1, User::count());
    }

    // ─── 5. NULL DATABASE IDENTITY ───────────────────────────────────

    public function test_it_refuses_on_null_database_identity(): void
    {
        $seeder = $this->createSeederWithDbIdentity(null);

        try {
            $seeder->run();
            $this->fail('A null database identity must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('database', $exception->getMessage());
        }

        $this->assertSame(0, User::count());
    }

    // ─── 6. EXISTING ACTIVE USER ─────────────────────────────────────

    public function test_it_refuses_when_username_already_exists_active(): void
    {
        $user = User::factory()->create([
            'username' => $this->testUsername,
            'is_active' => true,
        ]);
        // Baseline diambil dari DB agar tipe/urutan kolom identik dengan reload.
        $original = User::query()->findOrFail($user->id)->getAttributes();

        $seeder = $this->createSeederWithDbIdentity('db_preventive_maintenance');

        try {
            $seeder->run();
            $this->fail('An existing active username must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('exists', $exception->getMessage());
        }

        // Tidak ada duplikat dan tidak ada kolom apa pun yang berubah.
        $this->assertSame(1, User::count());
        $this->assertSame($original, User::query()->findOrFail($user->id)->getAttributes());
    }

    // ─── 7. EXISTING SOFT-DELETED USER ───────────────────────────────

    public function test_it_refuses_when_username_already_exists_soft_deleted(): void
    {
        $user = User::factory()->create(['username' => $this->testUsername]);
        $user->delete();
        $original = User::withTrashed()->findOrFail($user->id)->getAttributes();

        $seeder = $this->createSeederWithDbIdentity('db_preventive_maintenance');

        try {
            $seeder->run();
            $this->fail('An existing soft-deleted username must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('exists', $exception->getMessage());
        }

        // Tetap soft-deleted, tanpa restore, duplikat, atau perubahan kolom.
        $this->assertSame(1, User::withTrashed()->count());
        $unchanged = User::withTrashed()->findOrFail($user->id);
        $this->assertSame($original, $unchanged->getAttributes());
        $this->assertNotNull($unchanged->deleted_at);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_it_fails_closed_when_database_identity_resolver_fails(): void
    {
        // Query log dimulai setelah setup; resolver gagal sebelum query apa pun.
        DB::enableQueryLog();

        $seeder = $this->createSeederWithResolverFailure();

        try {
            $seeder->run();
            $this->fail('A resolver failure must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Unable to verify', $exception->getMessage());
        }

        // Tidak ada query User sama sekali — bukti fail-closed sebelum query/write.
        $this->assertEmpty(DB::getQueryLog());
        $this->assertSame(0, User::count());
    }

    // ─── 8. SUCCESSFUL LOCAL CREATE ──────────────────────────────────

    public function test_it_creates_admin_user_when_all_conditions_met(): void
    {
        $seeder = $this->createSeederWithDbIdentity('db_preventive_maintenance');

        $seeder->run();

        // Verifikasi satu user dibuat
        $user = User::where('username', $this->testUsername)->first();
        $this->assertNotNull($user);
        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check($this->testPassword, $user->password));
    }

    public function test_it_stores_whitespace_padded_password_unchanged(): void
    {
        $paddedPassword = '  Playwright@2026  ';
        config()->set('prime-local-bootstrap.password', $paddedPassword);

        $seeder = $this->createSeederWithDbIdentity('db_preventive_maintenance');

        $seeder->run();

        // Validasi memakai trim(), tetapi kredensial asli tetap disimpan apa adanya.
        $user = User::where('username', $this->testUsername)->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check($paddedPassword, $user->password));
        $this->assertFalse(Hash::check(trim($paddedPassword), $user->password));
    }

    // ─── 9. NO UNRELATED DATA MUTATION ───────────────────────────────

    public function test_it_does_not_create_unrelated_data(): void
    {
        $seeder = $this->createSeederWithDbIdentity('db_preventive_maintenance');

        $seeder->run();

        // Hanya User yang boleh bertambah
        $this->assertSame(1, User::count());
        $this->assertSame(0, Location::count());
        $this->assertSame(0, Machine::count());
    }
}
