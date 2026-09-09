<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class LocalPlaywrightAdminSeeder extends Seeder
{
    private const TARGET_DATABASE = 'db_preventive_maintenance';

    /**
     * Jalankan bootstrap satu admin lokal hanya setelah seluruh safety gate lulus.
     */
    public function run(): void
    {
        $this->assertLocalEnvironment();

        $username = trim((string) config('prime-local-bootstrap.username'));
        $password = (string) config('prime-local-bootstrap.password');

        $this->assertCredentials($username, $password);
        $this->assertTargetDatabase();

        // withTrashed() mencegah username soft-deleted dihidupkan kembali.
        if (User::withTrashed()->where('username', $username)->exists()) {
            throw new RuntimeException("Local Playwright admin username already exists: {$username}");
        }

        User::query()->create([
            'name' => $username,
            'username' => $username,
            'password' => $password,
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    /**
     * Validasi environment sebelum koneksi atau tabel User disentuh.
     */
    protected function assertLocalEnvironment(): void
    {
        if (app()->environment() !== 'local') {
            throw new RuntimeException('Local Playwright admin bootstrap requires the local environment.');
        }
    }

    /**
     * Validasi kedua kredensial tanpa pernah memasukkan password ke pesan error.
     */
    protected function assertCredentials(string $username, string $password): void
    {
        if ($username === '') {
            throw new RuntimeException('Local Playwright admin username is missing.');
        }

        if (trim($password) === '') {
            throw new RuntimeException('Local Playwright admin password is missing.');
        }
    }

    /**
     * Ambil identity database dari koneksi yang sama dengan User model.
     * Method protected memberi seam test tanpa mengganti koneksi production.
     */
    protected function resolveCurrentDatabaseName(): ?string
    {
        return (new User)->getConnection()->selectOne('SELECT DATABASE() AS database_name')->database_name;
    }

    /**
     * Tolak NULL, query failure, dan database selain target manager-authorized.
     */
    protected function assertTargetDatabase(): void
    {
        try {
            $databaseName = $this->resolveCurrentDatabaseName();
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to verify the current database identity.', 0, $exception);
        }

        if ($databaseName !== self::TARGET_DATABASE) {
            throw new RuntimeException('Local Playwright admin bootstrap requires the target database db_preventive_maintenance.');
        }
    }
}
