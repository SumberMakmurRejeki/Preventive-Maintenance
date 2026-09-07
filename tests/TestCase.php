<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Validasi target database sebelum Laravel melakukan bootstrap aplikasi.
     * Ini mencegah RefreshDatabase menyentuh database produksi secara tidak sengaja.
     */
    public function createApplication()
    {
        $connection = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? null);
        $database = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? null);

        $isSqliteInMemory = $connection === 'sqlite' && $database === ':memory:';
        $isDedicatedMySql = $connection === 'mysql' && $database === 'db_preventive_maintenance_test';

        if (! $isSqliteInMemory && ! $isDedicatedMySql) {
            throw new \RuntimeException(
                'Unsafe test database target. Allowed targets are sqlite/:memory: or mysql/db_preventive_maintenance_test.'
            );
        }

        return parent::createApplication();
    }
}
